<?php

namespace Tests\Feature\Inventory;

use App\Enums\ComponentMaterialStatus;
use App\Enums\DispatchStatus;
use App\Enums\InventoryState;
use App\Enums\MaterialResponsibility;
use App\Enums\MovementType;
use App\Enums\OrderMaterialStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Filament\Resources\OrderMaterialFlows\Pages\ListOrderMaterialFlows;
use App\Models\Article;
use App\Models\Client;
use App\Models\Component;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialDispatch;
use App\Models\Order;
use App\Models\Sample;
use App\Models\SampleRevision;
use App\Models\User;
use App\Services\Inventory\OrderMaterialFlowService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_acc_stock_arrival_action_visibility_lifecycle(): void
    {
        $client = Client::factory()->create();
        $warehouseUser = User::factory()->create();
        $warehouseUser->assignRole(UserRole::WAREHOUSE->value);
        $this->actingAs($warehouseUser);

        // 1. Pesanan baru dengan status awal EMPTY -> Tombol ACC Kedatangan Gudang TIDAK BOLEH muncul
        $fpOrder = Order::factory()->create([
            'client_id' => $client->getKey(),
            'work_scheme' => WorkScheme::FULL_PACKAGE,
            'quantity' => 100,
        ]);
        app(OrderMaterialFlowService::class)->initializeRequirements($fpOrder);

        $this->assertEquals(OrderMaterialStatus::EMPTY, $fpOrder->warehouse_material_status);
        Livewire::test(ListOrderMaterialFlows::class)
            ->assertActionHidden(TestAction::make('accStockArrival')->table($fpOrder));

        // 2. Setelah Mengajukan PO -> Status berubah ke ITEM_REQUEST -> Tombol ACC Kedatangan Gudang HARUS muncul
        app(OrderMaterialFlowService::class)->triggerPoRequest($fpOrder, 'PO Bahan 100 pcs');
        $fpOrder->refresh();
        $this->assertEquals(OrderMaterialStatus::ITEM_REQUEST, $fpOrder->warehouse_material_status);
        Livewire::test(ListOrderMaterialFlows::class)
            ->assertActionVisible(TestAction::make('accStockArrival')->table($fpOrder));

        // 3. Pesanan CMT -> Status awal EMPTY -> Tersembunyi, lalu setelah Konfirmasi Klien -> Status CLIENT_CONFIRMATION -> HARUS muncul
        $cmtOrder = Order::factory()->create([
            'client_id' => $client->getKey(),
            'work_scheme' => WorkScheme::CMT,
            'quantity' => 50,
        ]);
        app(OrderMaterialFlowService::class)->initializeRequirements($cmtOrder);

        Livewire::test(ListOrderMaterialFlows::class)
            ->assertActionHidden(TestAction::make('accStockArrival')->table($cmtOrder));

        app(OrderMaterialFlowService::class)->triggerClientConfirmation($cmtOrder, 'Klien mengirim kain');
        $cmtOrder->refresh();
        $this->assertEquals(OrderMaterialStatus::CLIENT_CONFIRMATION, $cmtOrder->warehouse_material_status);
        Livewire::test(ListOrderMaterialFlows::class)
            ->assertActionVisible(TestAction::make('accStockArrival')->table($cmtOrder));
    }

    public function test_production_lead_can_only_acc_production_receipt_and_cannot_run_warehouse_actions(): void
    {
        $client = Client::factory()->create();
        $prodUser = User::factory()->create();
        $prodUser->assignRole(UserRole::PRODUCTION_LEAD->value);

        $warehouseUser = User::factory()->create();
        $warehouseUser->assignRole(UserRole::WAREHOUSE->value);

        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'work_scheme' => WorkScheme::FULL_PACKAGE,
            'quantity' => 100,
        ]);
        app(OrderMaterialFlowService::class)->initializeRequirements($order);

        // A. Login sebagai KEPALA PRODUKSI
        $this->actingAs($prodUser);

        // 1. Pada order status STANDBY -> Mulai Cutting TIDAK BOLEH muncul bagi Kepala Produksi
        $order->warehouse_material_status = OrderMaterialStatus::STANDBY;
        $order->save();
        Livewire::test(ListOrderMaterialFlows::class)
            ->assertActionHidden(TestAction::make('startCutting')->table($order));

        // 2. Pada order status PREPARING_DELIVERY -> Kirim ke Produksi TIDAK BOLEH muncul bagi Kepala Produksi
        $order->warehouse_material_status = OrderMaterialStatus::PREPARING_DELIVERY;
        $order->save();
        Livewire::test(ListOrderMaterialFlows::class)
            ->assertActionHidden(TestAction::make('dispatchToProduction')->table($order));

        // 3. Pada order status EMPTY / ITEM_REQUEST -> Ajukan PO, Konfirmasi Klien, ACC Kedatangan Gudang TIDAK BOLEH muncul
        $order->warehouse_material_status = OrderMaterialStatus::EMPTY;
        $order->save();
        Livewire::test(ListOrderMaterialFlows::class)
            ->assertActionHidden(TestAction::make('requestPo')->table($order))
            ->assertActionHidden(TestAction::make('confirmClient')->table($order))
            ->assertActionHidden(TestAction::make('accStockArrival')->table($order));

        // 4. Pada order status DISPATCHED -> ACC Penerimaan HARUS muncul bagi Kepala Produksi
        $order->warehouse_material_status = OrderMaterialStatus::DISPATCHED;
        $order->save();
        Livewire::test(ListOrderMaterialFlows::class)
            ->assertActionVisible(TestAction::make('accReceiveProduction')->table($order));

        // B. Login sebagai GUDANG
        $this->actingAs($warehouseUser);

        // 1. Gudang BISA melihat Mulai Cutting saat STANDBY
        $order->warehouse_material_status = OrderMaterialStatus::STANDBY;
        $order->save();
        Livewire::test(ListOrderMaterialFlows::class)
            ->assertActionVisible(TestAction::make('startCutting')->table($order));

        // 2. Gudang BISA melihat Kirim ke Produksi saat PREPARING_DELIVERY
        $order->warehouse_material_status = OrderMaterialStatus::PREPARING_DELIVERY;
        $order->save();
        Livewire::test(ListOrderMaterialFlows::class)
            ->assertActionVisible(TestAction::make('dispatchToProduction')->table($order));

        // 3. Gudang TIDAK BISA melihat ACC Penerimaan saat DISPATCHED
        $order->warehouse_material_status = OrderMaterialStatus::DISPATCHED;
        $order->save();
        Livewire::test(ListOrderMaterialFlows::class)
            ->assertActionHidden(TestAction::make('accReceiveProduction')->table($order));
    }

    public function test_only_mass_production_orders_appear_in_order_material_flows_table(): void
    {
        $warehouseUser = User::factory()->create();
        $warehouseUser->assignRole(UserRole::WAREHOUSE->value);
        $this->actingAs($warehouseUser);

        $client = Client::factory()->create();

        // 1. Order pra-produksi (Belum dijadikan order massal)
        $requestedOrder = Order::factory()->create([
            'client_id' => $client->getKey(),
            'order_number' => 'ORD-REQ-PRE',
            'status' => OrderStatus::REQUESTED->value,
        ]);

        $quotedOrder = Order::factory()->create([
            'client_id' => $client->getKey(),
            'order_number' => 'ORD-QUOTED-PRE',
            'status' => OrderStatus::QUOTED->value,
        ]);

        $quoteApprovedOrder = Order::factory()->create([
            'client_id' => $client->getKey(),
            'order_number' => 'ORD-QUOTE-APP-PRE',
            'status' => OrderStatus::QUOTE_APPROVED->value,
        ]);

        $cancelledOrder = Order::factory()->create([
            'client_id' => $client->getKey(),
            'order_number' => 'ORD-CANCELLED',
            'status' => OrderStatus::CANCELLED->value,
        ]);

        // 2. Order yang sudah disahkan menjadi order massal (READY_FOR_PRODUCTION)
        $massOrder = Order::factory()->create([
            'client_id' => $client->getKey(),
            'order_number' => 'ORD-MASS-PROD-001',
            'status' => OrderStatus::READY_FOR_PRODUCTION->value,
        ]);

        Livewire::test(ListOrderMaterialFlows::class)
            ->assertCanSeeTableRecords([$massOrder])
            ->assertCanNotSeeTableRecords([
                $requestedOrder,
                $quotedOrder,
                $quoteApprovedOrder,
                $cancelledOrder,
            ]);
    }

    public function test_requirements_strictly_sourced_from_material_usage_not_components(): void
    {
        $client = Client::factory()->create();
        $article = Article::factory()->create(['client_id' => $client->getKey()]);

        // Artikel memiliki komponen tahapan jahit (Time Study)
        Component::create([
            'article_id' => $article->getKey(),
            'code' => 'CMP-01',
            'name' => 'Penjahitan Gagang Tas',
            'sequence' => 1,
        ]);

        $sample = Sample::factory()->create([
            'client_id' => $client->getKey(),
            'article_id' => $article->getKey(),
        ]);

        $revision = SampleRevision::factory()->create([
            'sample_id' => $sample->getKey(),
            'version' => 1,
            'material_usage' => [
                'Kain Cordura 500D' => '1.5 meter',
                'Resleting YKK No.5' => '1 pcs',
            ],
        ]);
        $sample->update(['approved_revision_id' => $revision->getKey()]);

        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'sample_id' => $sample->getKey(),
            'sample_revision_id' => $revision->getKey(),
            'work_scheme' => WorkScheme::FULL_PACKAGE,
            'quantity' => 100,
        ]);

        $service = app(OrderMaterialFlowService::class);
        $requirements = $service->initializeRequirements($order);

        // Kebutuhan barang gudang harus persis 2 item sesuai material_usage
        $this->assertCount(2, $requirements);

        // Kuantitas kebutuhan = konsumsi per pcs x kuantitas order (100 pcs)
        $cordura = $requirements->firstWhere('item_name', 'Kain Cordura 500D');
        $this->assertNotNull($cordura);
        $this->assertEquals(150.0, (float) $cordura->required_quantity);
        $this->assertEquals('meter', $cordura->uom);

        $zipper = $requirements->firstWhere('item_name', 'Resleting YKK No.5');
        $this->assertNotNull($zipper);
        $this->assertEquals(100.0, (float) $zipper->required_quantity);
        $this->assertEquals('pcs', $zipper->uom);

        // Validasi krusial: Tidak ada requirement gudang yang mengambil nama komponen jahit
        $this->assertFalse(
            $requirements->contains(fn ($r) => str_contains($r->item_name, 'Gagang Tas')),
            'Data penerimaan gudang tidak boleh berisi nama komponen tahapan jahit!'
        );
    }
}
