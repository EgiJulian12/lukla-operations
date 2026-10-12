<?php

namespace Tests\Feature\Inventory;

use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Exceptions\InventoryOwnershipMismatch;
use App\Filament\Resources\InventoryMovements\Pages\CreateInventoryMovement;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Skenario penerimaan #1 — proteksi kontaminasi silang stok (FR-007).
 *
 * Bahan milik Klien A tidak boleh keluar untuk pesanan Klien B. Yang diuji di
 * sini bukan hanya penolakan, tetapi juga bahwa penolakan itu punya DUA
 * lapisan yang independen: validasi service (pesan eksplisit ke pengguna) dan
 * foreign key komposit di PostgreSQL.
 */
class CrossClientIssueRejectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_issuing_material_for_another_clients_order_is_rejected(): void
    {
        $owner = Client::factory()->create();
        $otherClient = Client::factory()->create();

        $item = InventoryItem::factory()->withStock(100)->create([
            'client_id' => $owner->getKey(),
            'order_id' => Order::factory()->create(['client_id' => $owner->getKey()])->getKey(),
        ]);

        $foreignOrder = Order::factory()->create(['client_id' => $otherClient->getKey()]);

        try {
            app(InventoryService::class)->recordMovement(
                item: $item,
                type: MovementType::ISSUE_TO_CUTTING,
                quantity: 10,
                destination: $foreignOrder,
            );

            $this->fail('Pengeluaran lintas klien seharusnya ditolak.');
        } catch (InventoryOwnershipMismatch $exception) {
            $this->assertStringContainsString('pemilik tidak cocok', $exception->getMessage());
            $this->assertSame((int) $owner->getKey(), $exception->itemOwnership()['client_id']);
        }

        // Blokade terjadi SEBELUM transaksi tercatat (NFR-002).
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertEquals(100, (float) $item->fresh()->quantity);
    }

    /**
     * Lapisan ketiga, tanpa lewat service sama sekali: ditulis dengan query
     * mentah agar validasi aplikasi benar-benar dilewati. PostgreSQL sendiri
     * yang harus menolak, karena kolom klien/pesanan tidak cocok dengan
     * kepemilikan item stok.
     */
    public function test_database_rejects_a_mismatched_movement_written_without_the_service(): void
    {
        $owner = Client::factory()->create();
        $otherClient = Client::factory()->create();

        $item = InventoryItem::factory()->create([
            'client_id' => $owner->getKey(),
            'order_id' => Order::factory()->create(['client_id' => $owner->getKey()])->getKey(),
        ]);

        $foreignOrder = Order::factory()->create(['client_id' => $otherClient->getKey()]);

        $this->expectException(QueryException::class);

        DB::table('inventory_movements')->insert([
            'client_id' => $foreignOrder->client_id,
            'order_id' => $foreignOrder->getKey(),
            'inventory_item_id' => $item->getKey(),
            'movement_type' => MovementType::ISSUE_TO_CUTTING->value,
            'quantity' => -10,
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_issuing_for_the_items_own_order_succeeds_and_reduces_stock(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->getKey()]);

        $item = InventoryItem::factory()->withStock(100)->create([
            'client_id' => $client->getKey(),
            'order_id' => $order->getKey(),
        ]);

        $movement = app(InventoryService::class)->recordMovement(
            item: $item,
            type: MovementType::ISSUE_TO_CUTTING,
            quantity: 30,
            destination: $order,
        );

        // Perubahan bertanda: keluar selalu negatif pada buku besar.
        $this->assertEquals(-30, (float) $movement->quantity);
        $this->assertEquals(70, (float) $item->fresh()->quantity);
        $this->assertSame($order->getKey(), $movement->order_id);
    }

    /**
     * NFR-002 pada tingkat antarmuka: peringatan ketidakcocokan muncul sebagai
     * galat validasi pada field pesanan tujuan, di dalam aksi yang sama —
     * bukan sebagai galat 500 dari pelanggaran foreign key.
     */
    public function test_the_create_form_blocks_a_cross_client_issue_and_shows_the_warning(): void
    {
        $owner = Client::factory()->create();
        $otherClient = Client::factory()->create();

        $item = InventoryItem::factory()->withStock(100)->create([
            'client_id' => $owner->getKey(),
            'order_id' => Order::factory()->create(['client_id' => $owner->getKey()])->getKey(),
        ]);

        $foreignOrder = Order::factory()->create(['client_id' => $otherClient->getKey()]);

        $user = User::factory()->create();
        $user->assignRole(UserRole::WAREHOUSE->value);
        $this->actingAs($user);

        Livewire::test(CreateInventoryMovement::class)
            ->fillForm([
                'inventory_item_id' => $item->getKey(),
                'movement_type' => MovementType::ISSUE_TO_CUTTING->value,
                'destination_order_id' => $foreignOrder->getKey(),
                'quantity' => 10,
            ])
            ->call('create')
            ->assertHasFormErrors(['destination_order_id']);

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertEquals(100, (float) $item->fresh()->quantity);
    }
}
