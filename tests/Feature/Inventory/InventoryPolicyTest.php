<?php

namespace Tests\Feature\Inventory;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\User;
use App\Models\WasteRecord;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matriks RBAC untuk modul Gudang (BRD "Actor scope and permissions").
 *
 * Yang diuji adalah pernyataan peran — bukan kecocokan klien/pesanan, yang
 * sudah ditangani InventoryService dan FK komposit.
 */
class InventoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_warehouse_staff_may_receive_and_move_stock(): void
    {
        $user = $this->userWithRole(UserRole::WAREHOUSE);

        $this->assertTrue($user->can('create', InventoryItem::class));
        $this->assertTrue($user->can('create', InventoryMovement::class));
        $this->assertTrue($user->can('create', WasteRecord::class));
    }

    public function test_finance_may_view_but_never_change_physical_stock(): void
    {
        $user = $this->userWithRole(UserRole::FINANCE_PAYROLL);
        $item = InventoryItem::factory()->create();

        $this->assertTrue($user->can('viewAny', InventoryItem::class));
        $this->assertFalse($user->can('create', InventoryItem::class));
        $this->assertFalse($user->can('update', $item));
        $this->assertFalse($user->can('delete', $item));
    }

    public function test_quality_and_tailor_roles_do_not_touch_the_warehouse(): void
    {
        foreach ([UserRole::QC_INSPECTOR, UserRole::TAILOR_OPERATOR] as $role) {
            $user = $this->userWithRole($role);

            $this->assertFalse($user->can('create', InventoryItem::class), "Peran {$role->value} seharusnya tidak boleh menerima bahan.");
            $this->assertFalse($user->can('create', WasteRecord::class), "Peran {$role->value} seharusnya tidak boleh mencatat sisa potongan.");
        }
    }

    public function test_stock_is_never_deleted_even_by_the_warehouse(): void
    {
        $user = $this->userWithRole(UserRole::WAREHOUSE);
        $item = InventoryItem::factory()->create();

        $this->assertFalse($user->can('delete', $item));
    }

    public function test_movements_are_immutable(): void
    {
        $user = $this->userWithRole(UserRole::WAREHOUSE);
        $movement = InventoryMovement::factory()->create();

        $this->assertTrue($user->can('create', InventoryMovement::class));
        $this->assertFalse($user->can('update', $movement));
        $this->assertFalse($user->can('delete', $movement));
    }

    /**
     * Lapisan kedua isolasi: global scope BelongsToClient memotong query untuk
     * akun klien, tanpa bergantung pada panel Filament (NFR-004, FR-025).
     */
    public function test_client_user_only_sees_its_own_stock(): void
    {
        $ownClient = Client::factory()->create();
        $otherClient = Client::factory()->create();

        $this->itemFor($ownClient);
        $this->itemFor($otherClient);

        $clientUser = User::factory()->create(['client_id' => $ownClient->getKey()]);
        $clientUser->assignRole(UserRole::CLIENT_MAKLON->value);

        $this->actingAs($clientUser);

        $this->assertSame(1, InventoryItem::query()->count());
        $this->assertSame($ownClient->getKey(), InventoryItem::query()->sole()->client_id);
    }

    /**
     * Staf internal memang bekerja lintas klien, jadi scope tidak boleh aktif.
     */
    public function test_internal_staff_see_every_clients_stock(): void
    {
        $this->itemFor(Client::factory()->create());
        $this->itemFor(Client::factory()->create());

        $this->actingAs($this->userWithRole(UserRole::WAREHOUSE));

        $this->assertSame(2, InventoryItem::query()->count());
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    private function itemFor(Client $client): InventoryItem
    {
        return InventoryItem::factory()->create([
            'client_id' => $client->getKey(),
            'order_id' => Order::factory()->create(['client_id' => $client->getKey()])->getKey(),
        ]);
    }
}
