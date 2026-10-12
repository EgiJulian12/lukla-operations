<?php

namespace Tests\Feature\Inventory;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStock;
use App\Exceptions\InventoryOwnershipMismatch;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Material;
use App\Models\Order;
use App\Services\Inventory\InventoryService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Aturan tulis InventoryService: aritmetika stok, penjaga stok negatif, dan
 * kewajiban menyebut pesanan tujuan pada pergerakan keluar.
 *
 * Berada di Feature (bukan Unit) karena setiap kasusnya menyentuh database —
 * FK komposit dan invarian jumlah hanya bermakna bila barisnya benar-benar
 * ditulis.
 */
class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_receiving_creates_an_item_whose_quantity_comes_from_the_movement(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->getKey()]);

        $item = app(InventoryService::class)->receive(
            order: $order,
            material: Material::factory()->create(),
            quantity: 250,
            batch: 'B-01',
        );

        $this->assertEquals(250, (float) $item->quantity);
        $this->assertSame($order->getKey(), $item->order_id);

        // Invarian: jumlah item sama dengan jumlah seluruh pergerakannya.
        $this->assertEquals(
            (float) $item->quantity,
            (float) $item->movements()->sum('quantity'),
        );
    }

    public function test_issuing_more_than_available_is_rejected(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->getKey()]);
        $item = InventoryItem::factory()->withStock(5)->create([
            'client_id' => $client->getKey(),
            'order_id' => $order->getKey(),
        ]);

        $this->expectException(InsufficientStock::class);

        app(InventoryService::class)->recordMovement(
            item: $item,
            type: MovementType::ISSUE_TO_CUTTING,
            quantity: 20,
            destination: $order,
        );
    }

    public function test_adjustment_may_reduce_stock(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->getKey()]);
        $item = InventoryItem::factory()->withStock(40)->create([
            'client_id' => $client->getKey(),
            'order_id' => $order->getKey(),
        ]);

        app(InventoryService::class)->recordMovement(
            item: $item,
            type: MovementType::ADJUSTMENT,
            quantity: -15,
        );

        $this->assertEquals(25, (float) $item->fresh()->quantity);
    }

    public function test_issue_without_a_destination_order_is_rejected(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->getKey()]);
        $item = InventoryItem::factory()->withStock(40)->create([
            'client_id' => $client->getKey(),
            'order_id' => $order->getKey(),
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(InventoryService::class)->recordMovement(
            item: $item,
            type: MovementType::ISSUE_TO_CUTTING,
            quantity: 10,
        );
    }

    public function test_transfer_between_clients_is_rejected_like_an_issue(): void
    {
        $owner = Client::factory()->create();
        $other = Client::factory()->create();

        $item = InventoryItem::factory()->withStock(40)->create([
            'client_id' => $owner->getKey(),
            'order_id' => Order::factory()->create(['client_id' => $owner->getKey()])->getKey(),
        ]);

        $this->expectException(InventoryOwnershipMismatch::class);

        app(InventoryService::class)->recordMovement(
            item: $item,
            type: MovementType::TRANSFER,
            quantity: 10,
            destination: Order::factory()->create(['client_id' => $other->getKey()]),
        );
    }
}
