<?php

namespace Tests\Feature\Inventory;

use App\Enums\InventoryState;
use App\Exceptions\InvalidStateTransitionException;
use App\Exceptions\InventoryOwnershipMismatch;
use App\Models\Client;
use App\Models\Material;
use App\Models\Order;
use App\Models\WasteRecord;
use App\Services\Inventory\InventoryService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Pengujian Siklus Hidup Inventory dan Pencatatan Waste (FR-005, FR-008, FR-012, Modul 7).
 */
class InventorySequenceAndWasteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_valid_sequential_transition_from_raw_to_wip_and_finished(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->getKey()]);
        $service = app(InventoryService::class);

        $item = $service->receive(
            order: $order,
            material: Material::factory()->create(),
            quantity: 100,
        );

        $this->assertSame(InventoryState::RAW, $item->state);

        // RAW -> WIP
        $item = $service->transitionState($item, InventoryState::WIP, $order);
        $this->assertSame(InventoryState::WIP, $item->state);

        // WIP -> FINISHED
        $item = $service->transitionState($item, InventoryState::FINISHED, $order);
        $this->assertSame(InventoryState::FINISHED, $item->state);
    }

    public function test_skipping_transition_from_raw_directly_to_finished_is_rejected(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->getKey()]);
        $service = app(InventoryService::class);

        $item = $service->receive(
            order: $order,
            material: Material::factory()->create(),
            quantity: 100,
        );

        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage('RAW -> WIP -> FINISHED');

        $service->transitionState($item, InventoryState::FINISHED, $order);
    }

    public function test_backward_transition_is_rejected(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->getKey()]);
        $service = app(InventoryService::class);

        $item = $service->receive(
            order: $order,
            material: Material::factory()->create(),
            quantity: 100,
            state: InventoryState::WIP,
        );

        $this->expectException(InvalidStateTransitionException::class);

        $service->transitionState($item, InventoryState::RAW, $order);
    }

    public function test_transition_without_order_context_is_rejected(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->getKey()]);
        $service = app(InventoryService::class);

        $item = $service->receive(
            order: $order,
            material: Material::factory()->create(),
            quantity: 100,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('wajib menyertakan konteks pesanan');

        $service->transitionState($item, InventoryState::WIP, null);
    }

    public function test_transition_with_mismatched_client_order_is_rejected(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        $orderA = Order::factory()->create(['client_id' => $clientA->getKey()]);
        $orderB = Order::factory()->create(['client_id' => $clientB->getKey()]);
        $service = app(InventoryService::class);

        $item = $service->receive(
            order: $orderA,
            material: Material::factory()->create(),
            quantity: 100,
        );

        $this->expectException(InventoryOwnershipMismatch::class);

        $service->transitionState($item, InventoryState::WIP, $orderB);
    }

    public function test_recording_waste_stores_record_with_order_and_client_attribution(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->getKey()]);
        $material = Material::factory()->create();
        $service = app(InventoryService::class);

        $waste = $service->recordWaste(
            order: $order,
            material: $material,
            quantity: 3.5,
            category: 'Perca Sisa Pola',
            notes: 'Potongan ujung kain',
        );

        $this->assertInstanceOf(WasteRecord::class, $waste);
        $this->assertEquals(3.5, (float) $waste->quantity);
        $this->assertSame($client->getKey(), $waste->client_id);
        $this->assertSame($order->getKey(), $waste->order_id);
        $this->assertSame($material->getKey(), $waste->material_id);
        $this->assertSame('Perca Sisa Pola', $waste->category);
    }

    public function test_recording_waste_with_zero_or_negative_quantity_is_rejected(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->getKey()]);
        $service = app(InventoryService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lebih besar dari nol');

        $service->recordWaste(
            order: $order,
            material: null,
            quantity: 0,
        );
    }
}
