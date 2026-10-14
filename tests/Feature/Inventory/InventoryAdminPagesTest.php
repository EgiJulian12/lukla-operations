<?php

namespace Tests\Feature\Inventory;

use App\Enums\UserRole;
use App\Filament\Resources\InventoryItems\Pages\ListInventoryItems;
use App\Filament\Resources\InventoryMovements\Pages\CreateInventoryMovement;
use App\Filament\Resources\InventoryMovements\Pages\ListInventoryMovements;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Smoke test panel /admin untuk modul Gudang & Cutting.
 *
 * Feature test atas service saja tidak akan menangkap galat pada lapisan
 * Filament — tipe properti navigasi, skema form, kolom tabel. Karena itu
 * halaman daftar di sini benar-benar dirender, dan hak aksesnya diuji lewat
 * HTTP sehingga policy benar-benar dievaluasi oleh Filament.
 */
class InventoryAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_warehouse_staff_can_open_the_stock_grid(): void
    {
        $item = $this->itemFor(Client::factory()->create());

        $this->actingAs($this->warehouseUser())
            ->get('/admin/inventory-items')
            ->assertOk()
            ->assertSee($item->batch);
    }

    public function test_warehouse_staff_can_open_the_movement_history(): void
    {
        $this->actingAs($this->warehouseUser())
            ->get('/admin/inventory-movements')
            ->assertOk();
    }

    public function test_warehouse_staff_can_open_the_waste_records(): void
    {
        $this->actingAs($this->warehouseUser())
            ->get('/admin/waste-records')
            ->assertOk();
    }

    public function test_stock_grid_renders_as_a_livewire_component(): void
    {
        $this->itemFor(Client::factory()->create());

        $this->actingAs($this->warehouseUser());

        Livewire::test(ListInventoryItems::class)->assertOk();
    }

    public function test_movement_history_renders_as_a_livewire_component(): void
    {
        $this->actingAs($this->warehouseUser());

        Livewire::test(ListInventoryMovements::class)->assertOk();
    }

    public function test_movement_create_form_is_reachable(): void
    {
        $this->itemFor(Client::factory()->create());

        $this->actingAs($this->warehouseUser());

        Livewire::test(CreateInventoryMovement::class)->assertOk();
    }

    /**
     * Finance tidak memiliki akses ke inventory sama sekali — fitur stok
     * bukan domain Finance. canAccess() pada InventoryItemResource memblokir
     * akses baik ke daftar maupun formulir (Req TRACK A).
     */
    public function test_finance_user_cannot_access_inventory_at_all(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::FINANCE_PAYROLL->value);

        $this->actingAs($user)
            ->get('/admin/inventory-items')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/admin/inventory-items/create')
            ->assertForbidden();
    }

    public function test_warehouse_staff_can_open_the_material_dispatches(): void
    {
        $this->actingAs($this->warehouseUser())
            ->get('/admin/material-dispatches')
            ->assertOk();
    }

    public function test_production_lead_can_open_create_dispatch_but_warehouse_cannot(): void
    {
        $productionLead = User::factory()->create();
        $productionLead->assignRole(UserRole::PRODUCTION_LEAD->value);

        $this->actingAs($productionLead)
            ->get('/admin/material-dispatches/create')
            ->assertOk();

        $this->actingAs($this->warehouseUser())
            ->get('/admin/material-dispatches/create')
            ->assertForbidden();
    }

    public function test_warehouse_staff_can_view_print_tag_label(): void
    {
        $item = $this->itemFor(Client::factory()->create());

        $this->actingAs($this->warehouseUser())
            ->get("/admin/inventory-items/{$item->getKey()}/print-tag")
            ->assertOk()
            ->assertSee($item->tag_id);
    }

    public function test_client_user_cannot_reach_the_admin_panel_at_all(): void
    {
        $client = Client::factory()->create();

        $user = User::factory()->create(['client_id' => $client->getKey()]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        $this->actingAs($user)
            ->get('/admin/inventory-items')
            ->assertForbidden();
    }

    private function warehouseUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::WAREHOUSE->value);

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
