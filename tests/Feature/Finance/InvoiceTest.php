<?php

namespace Tests\Feature\Finance;

use App\Enums\InventoryState;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidInvoiceState;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invoice klien mengikuti urutan draf, terbit, lunas (matriks RBAC SRS,
 * Finance).
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_draft_invoice_can_be_issued(): void
    {
        $finance = User::factory()->create();
        $this->actingAs($finance);

        $invoice = Invoice::factory()->create(['issued_at' => null]);

        $issued = app(InvoiceService::class)->issue($invoice);

        $this->assertSame(InvoiceStatus::ISSUED, $issued->status);
        $this->assertNotNull($issued->issued_at);
        $this->assertSame($finance->getKey(), $issued->issued_by);
    }

    public function test_an_issued_invoice_cannot_be_issued_again(): void
    {
        $invoice = Invoice::factory()->issued()->create();

        $this->expectException(InvalidInvoiceState::class);

        app(InvoiceService::class)->issue($invoice);
    }

    public function test_an_issued_invoice_can_be_marked_paid(): void
    {
        $invoice = Invoice::factory()->issued()->create();

        $paid = app(InvoiceService::class)->markPaid($invoice);

        $this->assertSame(InvoiceStatus::PAID, $paid->status);
    }

    public function test_a_draft_invoice_cannot_be_marked_paid(): void
    {
        $invoice = Invoice::factory()->create();

        $this->expectException(InvalidInvoiceState::class);

        app(InvoiceService::class)->markPaid($invoice);
    }

    public function test_client_can_confirm_payment_on_issued_invoice(): void
    {
        $invoice = Invoice::factory()->issued()->create();

        $confirmed = app(InvoiceService::class)->confirmPayment($invoice, [
            'payment_method' => 'Transfer Bank BCA',
            'payment_notes' => 'Transfer atas nama PT Maju, Ref 12345678',
        ]);

        $this->assertSame(InvoiceStatus::PAYMENT_CONFIRMED, $confirmed->status);
        $this->assertNotNull($confirmed->payment_confirmed_at);
        $this->assertSame('Transfer Bank BCA', $confirmed->payment_method);
        $this->assertSame('Transfer atas nama PT Maju, Ref 12345678', $confirmed->payment_notes);
    }

    public function test_a_payment_confirmed_invoice_can_be_marked_paid(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'status' => InvoiceStatus::PAYMENT_CONFIRMED,
            'payment_confirmed_at' => now(),
        ]);

        $paid = app(InvoiceService::class)->markPaid($invoice);

        $this->assertSame(InvoiceStatus::PAID, $paid->status);
    }

    public function test_marking_invoice_paid_completes_the_order_and_inventory_items(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::IN_PRODUCTION,
        ]);

        $item1 = InventoryItem::factory()->create([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'state' => InventoryState::RAW,
        ]);

        $item2 = InventoryItem::factory()->create([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'state' => InventoryState::WIP,
        ]);

        $invoice = Invoice::factory()->issued()->create([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'status' => InvoiceStatus::PAYMENT_CONFIRMED,
        ]);

        $paid = app(InvoiceService::class)->markPaid($invoice);

        $this->assertSame(InvoiceStatus::PAID, $paid->status);

        $order->refresh();
        $this->assertSame(OrderStatus::COMPLETED, $order->status);
        $this->assertNotNull($order->completed_at);

        $item1->refresh();
        $item2->refresh();
        $this->assertSame(InventoryState::FINISHED, $item1->state);
        $this->assertSame(InventoryState::FINISHED, $item2->state);
    }
}
