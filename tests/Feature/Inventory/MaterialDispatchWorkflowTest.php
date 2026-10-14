<?php

namespace Tests\Feature\Inventory;

use App\Enums\DispatchStatus;
use App\Enums\InventoryState;
use App\Enums\UserRole;
use App\Exceptions\InsufficientStock;
use App\Models\Client;
use App\Models\Material;
use App\Models\MaterialDispatch;
use App\Models\Order;
use App\Models\User;
use App\Models\WasteRecord;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\MaterialDispatchService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian Alur Pelacakan Permintaan dan Pengantaran Bahan ke Produksi / Cutting (FR-012, Modul 7C).
 */
class MaterialDispatchWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_production_can_create_dispatch_request_but_warehouse_alone_cannot(): void
    {
        $productionLead = User::factory()->create();
        $productionLead->assignRole(UserRole::PRODUCTION_LEAD->value);

        $warehouseUser = User::factory()->create();
        $warehouseUser->assignRole(UserRole::WAREHOUSE->value);

        // Production lead boleh create
        $this->assertTrue($productionLead->can('create', MaterialDispatch::class));

        // Warehouse sendirian TIDAK BOLEH create pengajuan
        $this->assertFalse($warehouseUser->can('create', MaterialDispatch::class));
    }

    public function test_requesting_more_than_available_stock_is_rejected(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->getKey()]);
        $productionLead = User::factory()->create();

        $item = app(InventoryService::class)->receive(
            order: $order,
            material: Material::factory()->create(),
            quantity: 50,
        );

        $this->expectException(InsufficientStock::class);

        app(MaterialDispatchService::class)->createRequest(
            item: $item,
            quantity: 100, // melebihi stok 50
            requestedBy: $productionLead,
        );
    }

    public function test_warehouse_can_reject_dispatch_request_with_reason(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->getKey()]);
        $productionLead = User::factory()->create();
        $warehouseUser = User::factory()->create();
        $dispatchService = app(MaterialDispatchService::class);

        $item = app(InventoryService::class)->receive(
            order: $order,
            material: Material::factory()->create(),
            quantity: 50,
        );

        $dispatch = $dispatchService->createRequest(
            item: $item,
            quantity: 20,
            requestedBy: $productionLead,
        );

        $this->assertSame(DispatchStatus::REQUESTED, $dispatch->status);

        $rejected = $dispatchService->reject($dispatch, $warehouseUser, 'Kain sedang di-quarantine untuk uji shrinkage');

        $this->assertSame(DispatchStatus::REJECTED, $rejected->status);
        $this->assertSame('Kain sedang di-quarantine untuk uji shrinkage', $rejected->rejection_reason);
    }

    public function test_end_to_end_dispatch_workflow_from_request_to_cutting_completion(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->getKey()]);
        $material = Material::factory()->create();
        $productionLead = User::factory()->create();
        $warehouseUser = User::factory()->create();
        $cutterUser = User::factory()->create();

        $inventoryService = app(InventoryService::class);
        $dispatchService = app(MaterialDispatchService::class);

        // 1. Gudang menerima 100 meter kain (status RAW)
        $item = $inventoryService->receive(
            order: $order,
            material: $material,
            quantity: 100,
        );

        $this->assertSame(InventoryState::RAW, $item->state);
        $this->assertNotEmpty($item->tag_id);

        // 2. Produksi mengajukan 40 meter
        $dispatch = $dispatchService->createRequest(
            item: $item,
            quantity: 40,
            requestedBy: $productionLead,
            notes: 'Cutting pola kemeja batch 1',
        );
        $this->assertSame(DispatchStatus::REQUESTED, $dispatch->status);

        // 3. Gudang menyetujui
        $dispatch = $dispatchService->approve($dispatch, $warehouseUser);
        $this->assertSame(DispatchStatus::APPROVED, $dispatch->status);

        // 4. Gudang mengirimkan bahan fisik
        $dispatch = $dispatchService->dispatch($dispatch, $warehouseUser);
        $this->assertSame(DispatchStatus::DISPATCHED, $dispatch->status);

        // 5. Produksi mengonfirmasi penerimaan
        $dispatch = $dispatchService->confirmReceived($dispatch, $productionLead);
        $this->assertSame(DispatchStatus::RECEIVED, $dispatch->status);

        // 6. Ditempatkan di antrian meja cutting
        $dispatch = $dispatchService->setIdleInQueue($dispatch);
        $this->assertSame(DispatchStatus::IDLE, $dispatch->status);

        // 7. Mulai dipotong
        $dispatch = $dispatchService->startCutting($dispatch);
        $this->assertSame(DispatchStatus::IN_CUTTING, $dispatch->status);

        // 8. Selesai potong: 38 meter jadi pola kemeja, 2 meter sisa perca/scrap
        $dispatch = $dispatchService->completeCutting(
            dispatch: $dispatch,
            usedQuantity: 38,
            scrapQuantity: 2,
            wasteCategory: 'Perca Sisa Potongan Pola',
            wasteNotes: 'Potongan sudut ujung gulungan',
        );

        $this->assertSame(DispatchStatus::COMPLETED, $dispatch->status);

        // Verifikasi stok gudang: 100 - (38 + 2) = 60 meter tersisa
        $item = $item->fresh();
        $this->assertEquals(60, (float) $item->quantity);

        // Verifikasi state item berpindah ke WIP
        $this->assertSame(InventoryState::WIP, $item->state);

        // Verifikasi otomatis tercatat di WasteRecord
        $this->assertDatabaseHas('waste_records', [
            'client_id' => $client->getKey(),
            'order_id' => $order->getKey(),
            'material_id' => $material->getKey(),
            'quantity' => 2,
            'category' => 'Perca Sisa Potongan Pola',
        ]);
    }
}
