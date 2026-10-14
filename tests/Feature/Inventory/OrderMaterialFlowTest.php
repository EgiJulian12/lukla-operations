<?php

namespace Tests\Feature\Inventory;

use App\Enums\ComponentMaterialStatus;
use App\Enums\DispatchStatus;
use App\Enums\InventoryState;
use App\Enums\MaterialResponsibility;
use App\Enums\MovementType;
use App\Enums\OrderMaterialStatus;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialDispatch;
use App\Models\Order;
use App\Models\User;
use App\Services\Inventory\OrderMaterialFlowService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian Alur Pergerakan Bahan Berdasarkan Pesanan (Order-Centric Warehouse & Cutting Hub).
 */
class OrderMaterialFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_order_initial_warehouse_status_is_empty(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'work_scheme' => WorkScheme::CMT,
        ]);

        $this->assertEquals(OrderMaterialStatus::EMPTY, $order->warehouse_material_status);
    }

    public function test_service_initializes_requirements_based_on_work_scheme(): void
    {
        $client = Client::factory()->create();
        $service = app(OrderMaterialFlowService::class);

        // 1. Skema CMT (Bahan dari Klien)
        $cmtOrder = Order::factory()->create([
            'client_id' => $client->getKey(),
            'work_scheme' => WorkScheme::CMT,
            'quantity' => 100,
        ]);
        $cmtReqs = $service->initializeRequirements($cmtOrder);
        $this->assertNotEmpty($cmtReqs);
        $this->assertEquals(MaterialResponsibility::CLIENT, $cmtReqs->first()->responsibility);

        // 2. Skema Full Package (Bahan dari Perusahaan)
        $fpOrder = Order::factory()->create([
            'client_id' => $client->getKey(),
            'work_scheme' => WorkScheme::FULL_PACKAGE,
            'quantity' => 100,
        ]);
        $fpReqs = $service->initializeRequirements($fpOrder);
        $this->assertEquals(MaterialResponsibility::COMPANY, $fpReqs->first()->responsibility);
    }

    public function test_po_and_client_confirmation_transitions(): void
    {
        $client = Client::factory()->create();
        $service = app(OrderMaterialFlowService::class);

        // Order Full Package -> Trigger PO
        $fpOrder = Order::factory()->create([
            'client_id' => $client->getKey(),
            'work_scheme' => WorkScheme::FULL_PACKAGE,
            'quantity' => 100,
        ]);
        $service->initializeRequirements($fpOrder);

        $service->triggerPoRequest($fpOrder, 'PO kain katun fleece 150m');
        $fpOrder->refresh();
        $this->assertEquals(OrderMaterialStatus::ITEM_REQUEST, $fpOrder->warehouse_material_status);
        $this->assertNotNull($fpOrder->po_requested_at);

        // Order CMT -> Trigger Konfirmasi Klien
        $cmtOrder = Order::factory()->create([
            'client_id' => $client->getKey(),
            'work_scheme' => WorkScheme::CMT,
            'quantity' => 100,
        ]);
        $service->initializeRequirements($cmtOrder);

        $service->triggerClientConfirmation($cmtOrder, 'Klien janji kirim tgl 16 Okt');
        $cmtOrder->refresh();
        $this->assertEquals(OrderMaterialStatus::CLIENT_CONFIRMATION, $cmtOrder->warehouse_material_status);
        $this->assertNotNull($cmtOrder->client_confirmed_at);

        // Order Semi-CMT (Kedua aksi aktif) -> Status gabungan
        $semiOrder = Order::factory()->create([
            'client_id' => $client->getKey(),
            'work_scheme' => WorkScheme::SEMI_CMT,
            'quantity' => 100,
        ]);
        $service->initializeRequirements($semiOrder);

        $service->triggerPoRequest($semiOrder, 'PO Aksesoris ke finance');
        $service->triggerClientConfirmation($semiOrder, 'Konfirmasi kain utama ke klien');
        $semiOrder->refresh();
        $this->assertEquals(OrderMaterialStatus::PO_AND_CLIENT, $semiOrder->warehouse_material_status);
    }

    public function test_stock_receipt_auto_transitions_to_standby_when_demand_met(): void
    {
        $client = Client::factory()->create();
        Material::factory()->create(['client_id' => $client->getKey(), 'category' => 'fabric', 'name' => 'Kain Cotton']);
        Material::factory()->create(['client_id' => $client->getKey(), 'category' => 'accessory', 'name' => 'Kancing']);
        Material::factory()->create(['client_id' => $client->getKey(), 'category' => 'trim', 'name' => 'Label']);
        $service = app(OrderMaterialFlowService::class);

        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'work_scheme' => WorkScheme::CMT,
            'quantity' => 10,
        ]);

        $requirements = $service->initializeRequirements($order);
        $this->assertCount(3, $requirements);

        // Masukkan stok untuk item 1 (belum lengkap semua -> status DELIVERY_IN_TRANSIT)
        $req1 = $requirements[0];
        $service->recordStockReceipt($req1, (float) $req1->required_quantity, 'BATCH-01', 'Klien', 'Kain sampai');
        $order->refresh();
        $this->assertEquals(OrderMaterialStatus::DELIVERY_IN_TRANSIT, $order->warehouse_material_status);

        // Masukkan stok untuk item 2
        $req2 = $requirements[1];
        $service->recordStockReceipt($req2, (float) $req2->required_quantity, 'LOT-ACC-01', 'Supplier', 'Kancing sampai');
        $order->refresh();
        $this->assertEquals(OrderMaterialStatus::DELIVERY_IN_TRANSIT, $order->warehouse_material_status);

        // Masukkan stok untuk item 3 (seluruh kebutuhan terpenuhi) -> Status otomatis STANDBY!
        $req3 = $requirements[2];
        $service->recordStockReceipt($req3, (float) $req3->required_quantity, 'LOT-TRIM-01', 'Supplier', 'Label sampai');
        $order->refresh();
        $this->assertEquals(OrderMaterialStatus::STANDBY, $order->warehouse_material_status);
        $this->assertTrue($order->isAllMaterialsFulfilled());

        // Pastikan seluruh item yang diterima otomatis masuk ke data Stok Gudang (inventory_items & movements)
        $this->assertDatabaseHas('inventory_items', [
            'client_id' => $client->getKey(),
            'order_id' => $order->getKey(),
            'state' => InventoryState::RAW->value,
        ]);

        $this->assertEquals(3, InventoryItem::where('order_id', $order->getKey())->count());
        $this->assertEquals(3, InventoryMovement::where('order_id', $order->getKey())->count());
    }

    public function test_cutting_dispatch_and_production_lead_acc_receipt(): void
    {
        $client = Client::factory()->create();
        $service = app(OrderMaterialFlowService::class);

        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'work_scheme' => WorkScheme::CMT,
            'quantity' => 10,
        ]);
        $requirements = $service->initializeRequirements($order);

        // Penuhi seluruh stok agar standby
        foreach ($requirements as $req) {
            $service->recordStockReceipt($req, (float) $req->required_quantity);
        }
        $order->refresh();
        $this->assertEquals(OrderMaterialStatus::STANDBY, $order->warehouse_material_status);

        // 1. Gudang Mulai Cutting
        $service->startCuttingAndPreparation($order);
        $order->refresh();
        $this->assertEquals(OrderMaterialStatus::PREPARING_DELIVERY, $order->warehouse_material_status);
        $this->assertNotNull($order->cutting_started_at);

        // 2. Gudang Kirim ke Produksi
        $service->dispatchToProduction($order);
        $order->refresh();
        $this->assertEquals(OrderMaterialStatus::DISPATCHED, $order->warehouse_material_status);
        $this->assertNotNull($order->dispatched_to_production_at);

        // Verifikasi otomatis tercatat di pengantaran bahan ke produksi (MaterialDispatch)
        $this->assertDatabaseHas('material_dispatches', [
            'order_id' => $order->getKey(),
            'status' => DispatchStatus::DISPATCHED->value,
        ]);
        $this->assertEquals($requirements->count(), MaterialDispatch::where('order_id', $order->getKey())->count());

        // Verifikasi mutasi pergerakan stok: Keluar ke Cutting & Transfer ke Produksi tercatat
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->getKey(),
            'movement_type' => MovementType::ISSUE_TO_CUTTING->value,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->getKey(),
            'movement_type' => MovementType::TRANSFER->value,
        ]);

        // 3. Kepala Produksi ACC Penerimaan Bahan
        $prodLead = User::factory()->create();
        $prodLead->assignRole(UserRole::PRODUCTION_LEAD->value);

        $service->confirmReceivedByProduction($order, $prodLead, 'Kain terpotong dan aksesoris lengkap');
        $order->refresh();
        $this->assertEquals(OrderMaterialStatus::RECEIVED_BY_PRODUCTION, $order->warehouse_material_status);
        $this->assertNotNull($order->received_by_production_at);
        $this->assertStringContainsString('Kain terpotong dan aksesoris lengkap', (string) $order->notes);
        $this->assertEquals(ComponentMaterialStatus::RECEIVED, $order->materialRequirements()->first()->status);

        // Verifikasi status di MaterialDispatch juga otomatis berpindah ke RECEIVED
        $this->assertDatabaseHas('material_dispatches', [
            'order_id' => $order->getKey(),
            'status' => DispatchStatus::RECEIVED->value,
        ]);
    }

    public function test_order_material_flow_resource_is_accessible_by_warehouse_and_production_lead(): void
    {
        $warehouseUser = User::factory()->create();
        $warehouseUser->assignRole(UserRole::WAREHOUSE->value);

        $prodUser = User::factory()->create();
        $prodUser->assignRole(UserRole::PRODUCTION_LEAD->value);

        $this->actingAs($warehouseUser);
        $response = $this->get('/admin/order-material-flows');
        $response->assertSuccessful();

        $this->actingAs($prodUser);
        $response = $this->get('/admin/order-material-flows');
        $response->assertSuccessful();
    }
}
