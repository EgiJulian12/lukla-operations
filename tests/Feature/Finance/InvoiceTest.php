<?php

namespace Tests\Feature\Finance;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvalidInvoiceState;
use App\Models\Invoice;
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
}
