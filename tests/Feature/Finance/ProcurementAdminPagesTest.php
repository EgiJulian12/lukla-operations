<?php

namespace Tests\Feature\Finance;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test panel /admin untuk PO bahan dan invoice.
 *
 * Halaman benar-benar dirender supaya galat lapisan Filament — ikon, kolom
 * tabel, skema form dengan repeater rincian — ikut tertangkap, bukan hanya
 * service-nya.
 */
class ProcurementAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_finance_staff_can_open_the_purchase_order_list(): void
    {
        $order = PurchaseOrder::factory()->create();

        $this->actingAs($this->financeUser())
            ->get('/admin/purchase-orders')
            ->assertOk()
            ->assertSee($order->po_number);
    }

    public function test_finance_staff_can_open_the_invoice_list(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->financeUser())
            ->get('/admin/invoices')
            ->assertOk()
            ->assertSee($invoice->invoice_number);
    }

    public function test_finance_staff_can_open_the_purchase_order_form(): void
    {
        $this->actingAs($this->financeUser())
            ->get('/admin/purchase-orders/create')
            ->assertOk();
    }

    public function test_finance_staff_can_open_the_invoice_form(): void
    {
        $this->actingAs($this->financeUser())
            ->get('/admin/invoices/create')
            ->assertOk();
    }

    private function financeUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::FINANCE_PAYROLL->value);

        return $user;
    }
}
