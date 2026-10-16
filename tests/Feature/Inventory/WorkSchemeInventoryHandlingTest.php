<?php

namespace Tests\Feature\Inventory;

use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\WorkScheme;
use App\Exceptions\InventoryOwnershipMismatch;
use App\Models\Client;
use App\Models\Material;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Services\Inventory\InventoryService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian Alur Skema Maklon (CMT, Semi-CMT, Full Package) & Isolasi Stok (FR-001, FR-006, FR-007, Modul 7B).
 */
class WorkSchemeInventoryHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_cmt_scheme_receives_client_materials_with_delivery_note(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'work_scheme' => WorkScheme::CMT,
        ]);
        $material = Material::factory()->create();
        $service = app(InventoryService::class);

        $item = $service->receiveFromClient(
            order: $order,
            material: $material,
            quantity: 350.5,
            deliveryNote: 'SJ-CLIENT-2026-001',
            batch: 'LOT-KLIEN-A',
        );

        $this->assertEquals(350.5, (float) $item->quantity);
        $this->assertSame($client->getKey(), $item->client_id);
        $this->assertSame($order->getKey(), $item->order_id);

        $movement = $item->movements()->first();
        $this->assertNotNull($movement);
        $this->assertStringContainsString('SJ-CLIENT-2026-001', $movement->reference);
    }

    public function test_full_package_scheme_initiates_purchase_order_draft_to_finance(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'work_scheme' => WorkScheme::FULL_PACKAGE,
        ]);
        $material = Material::factory()->create();
        $service = app(InventoryService::class);

        $po = $service->requestProcurementToFinance(
            order: $order,
            material: $material,
            quantity: 500,
            estimatedPrice: 25000,
            supplier: 'PT Tekstil Jaya Sentosa',
            notes: 'Bahan utama pesanan full package',
        );

        $this->assertInstanceOf(PurchaseOrder::class, $po);
        $this->assertSame(PurchaseOrderStatus::DRAFT, $po->status);
        $this->assertSame($order->getKey(), $po->order_id);
        $this->assertSame($client->getKey(), $po->client_id);
        $this->assertSame('PT Tekstil Jaya Sentosa', $po->supplier);

        // Periksa item PO
        $poItem = $po->items()->first();
        $this->assertNotNull($poItem);
        $this->assertEquals(500, (float) $poItem->quantity);
        $this->assertEquals(25000, (float) $poItem->unit_price);
    }

    public function test_semi_cmt_scheme_tags_lukla_accessories_with_client_and_order_isolation(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'work_scheme' => WorkScheme::SEMI_CMT,
        ]);
        $accessory = Material::factory()->create(['name' => 'Resleting YKK 50cm']);
        $service = app(InventoryService::class);

        $item = $service->tagSemiCmtAccessories(
            order: $order,
            material: $accessory,
            quantity: 200,
            batch: 'ZIP-2026',
        );

        $this->assertSame($client->getKey(), $item->client_id);
        $this->assertSame($order->getKey(), $item->order_id);
        $this->assertEquals(200, (float) $item->quantity);

        $movement = $item->movements()->first();
        $this->assertNotNull($movement);
        $this->assertStringContainsString('Semi-CMT', $movement->reference);
    }

    public function test_semi_cmt_accessories_cannot_be_issued_to_other_clients(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        $orderA = Order::factory()->create(['client_id' => $clientA->getKey()]);
        $orderB = Order::factory()->create(['client_id' => $clientB->getKey()]);
        $service = app(InventoryService::class);

        $item = $service->tagSemiCmtAccessories(
            order: $orderA,
            material: Material::factory()->create(),
            quantity: 100,
        );

        $this->expectException(InventoryOwnershipMismatch::class);

        $service->recordMovement(
            item: $item,
            type: MovementType::ISSUE_TO_CUTTING,
            quantity: 50,
            destination: $orderB,
        );
    }
}
