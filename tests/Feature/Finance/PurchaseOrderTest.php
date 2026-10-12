<?php

namespace Tests\Feature\Finance;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidPurchaseOrderState;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Services\Finance\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PO bahan mengikuti urutan draf lalu disetujui, dan nilainya diturunkan dari
 * rincian bahan (matriks RBAC SRS, Finance).
 */
class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_draft_purchase_order_can_be_approved(): void
    {
        $finance = User::factory()->create();
        $this->actingAs($finance);

        $order = PurchaseOrder::factory()->create();

        $approved = app(PurchaseOrderService::class)->approve($order);

        $this->assertSame(PurchaseOrderStatus::APPROVED, $approved->status);
        $this->assertSame($finance->getKey(), $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
    }

    public function test_an_approved_purchase_order_cannot_be_approved_again(): void
    {
        $order = PurchaseOrder::factory()->approved()->create();

        $this->expectException(InvalidPurchaseOrderState::class);

        app(PurchaseOrderService::class)->approve($order);
    }

    public function test_the_order_total_is_the_sum_of_its_lines(): void
    {
        $order = PurchaseOrder::factory()->create();

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->getKey(),
            'quantity' => 10,
            'unit_price' => 15000,
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->getKey(),
            'quantity' => 2,
            'unit_price' => 50000,
        ]);

        $this->assertSame(250000.0, $order->totalAmount());
    }
}
