<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\ApprovedSamples\ApprovedSampleResource;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Assemblies\AssemblyResource;
use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\MyPurchaseOrders\MyPurchaseOrderResource;
use App\Filament\Resources\OutputEntries\OutputEntryResource;
use App\Filament\Resources\OutputReviews\OutputReviewResource;
use App\Filament\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\Resources\ProductionLines\ProductionLineResource;
use App\Filament\Resources\QcOrders\QcOrderResource;
use App\Filament\Resources\QcRecords\QcRecordResource;
use App\Filament\Resources\RateCards\RateCardResource;
use App\Filament\Resources\Repairs\RepairResource;
use App\Filament\Resources\Samples\SampleResource;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMenuAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_tailor_only_sees_output_entry_in_sidebar(): void
    {
        $tailor = User::factory()->create();
        $tailor->assignRole(UserRole::TAILOR_OPERATOR->value);

        $this->actingAs($tailor);

        $this->assertTrue(OutputEntryResource::shouldRegisterNavigation());
        $this->assertFalse(ProductionLineResource::shouldRegisterNavigation());
        $this->assertFalse(ArticleResource::shouldRegisterNavigation());
        $this->assertFalse(InventoryItemResource::shouldRegisterNavigation());
        $this->assertFalse(PayrollPeriodResource::shouldRegisterNavigation());

        // Verifikasi output per order adalah wewenang Kepala Produksi, bukan penjahit.
        $this->assertFalse(OutputReviewResource::canAccess());
    }

    public function test_qc_inspector_only_sees_qc_and_defect_categories(): void
    {
        $qc = User::factory()->create();
        $qc->assignRole(UserRole::QC_INSPECTOR->value);

        $this->actingAs($qc);

        $this->assertTrue(QcRecordResource::shouldRegisterNavigation());
        $this->assertFalse(SampleResource::shouldRegisterNavigation());
        $this->assertFalse(InventoryItemResource::shouldRegisterNavigation());
        $this->assertFalse(PayrollPeriodResource::shouldRegisterNavigation());
    }

    public function test_warehouse_sees_inventory_and_materials_but_not_samples_or_payroll(): void
    {
        $warehouse = User::factory()->create();
        $warehouse->assignRole(UserRole::WAREHOUSE->value);

        $this->actingAs($warehouse);

        $this->assertTrue(InventoryItemResource::shouldRegisterNavigation());
        $this->assertFalse(SampleResource::shouldRegisterNavigation());
        $this->assertFalse(PayrollPeriodResource::shouldRegisterNavigation());
        $this->assertFalse(InvoiceResource::shouldRegisterNavigation());
    }

    public function test_sample_team_sees_samples_and_articles_but_not_inventory_or_payroll(): void
    {
        $sampleTeam = User::factory()->create();
        $sampleTeam->assignRole(UserRole::SAMPLE_TEAM->value);

        $this->actingAs($sampleTeam);

        $this->assertTrue(SampleResource::shouldRegisterNavigation());
        $this->assertTrue(ArticleResource::shouldRegisterNavigation());
        $this->assertFalse(InventoryItemResource::shouldRegisterNavigation());
        $this->assertFalse(PayrollPeriodResource::shouldRegisterNavigation());
        $this->assertFalse(ApprovedSampleResource::shouldRegisterNavigation());
    }

    public function test_finance_sees_payroll_and_invoices_but_not_physical_inventory(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole(UserRole::FINANCE_PAYROLL->value);

        $this->actingAs($finance);

        $this->assertTrue(PayrollPeriodResource::shouldRegisterNavigation());
        $this->assertTrue(InvoiceResource::shouldRegisterNavigation());
        $this->assertFalse(InventoryItemResource::shouldRegisterNavigation());
        $this->assertFalse(SampleResource::shouldRegisterNavigation());
        $this->assertFalse(ApprovedSampleResource::shouldRegisterNavigation());
    }

    public function test_owner_and_production_lead_can_see_approved_samples_in_sidebar(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $this->actingAs($owner);
        $this->assertTrue(ApprovedSampleResource::shouldRegisterNavigation());

        $productionLead = User::factory()->create();
        $productionLead->assignRole(UserRole::PRODUCTION_LEAD->value);

        $this->actingAs($productionLead);

        $this->assertTrue(ApprovedSampleResource::shouldRegisterNavigation());
        $this->assertFalse(SampleResource::shouldRegisterNavigation());

        // Output 30 Menit milik Kepala Produksi berbariskan order dan tinggal di
        // menu Produksi; log datar OutputEntryResource tetap ada sebagai pintu
        // masuk penjahit, bukan sebagai menu mandor.
        $this->assertFalse(OutputEntryResource::shouldRegisterNavigation());
        $this->assertTrue(OutputReviewResource::canAccess());
    }

    /**
     * Menu Quality Control dan Payroll & Biaya berhenti di perannya masing-masing.
     *
     * Kepala Produksi memimpin lantai produksi, bukan inspeksi QC maupun biaya:
     * QC dijalankan QC Inspector, tarif dan biaya dipegang Finance bersama
     * Owner. Yang diperiksa di sini baris-barisnya, sedangkan uji berikutnya
     * memeriksa hasilnya di sidebar.
     */
    public function test_production_lead_sees_neither_the_qc_nor_the_cost_menu(): void
    {
        $productionLead = User::factory()->create();
        $productionLead->assignRole(UserRole::PRODUCTION_LEAD->value);

        $this->actingAs($productionLead);

        $this->assertFalse(QcOrderResource::shouldRegisterNavigation());
        $this->assertFalse(QcRecordResource::shouldRegisterNavigation());
        $this->assertFalse(RepairResource::shouldRegisterNavigation());

        $this->assertFalse(RateCardResource::shouldRegisterNavigation());
        $this->assertFalse(PayrollPeriodResource::shouldRegisterNavigation());
        $this->assertFalse(InvoiceResource::shouldRegisterNavigation());

        // Menu produksinya sendiri tidak ikut tersapu oleh penyaringan ini.
        $this->assertTrue(ApprovedSampleResource::shouldRegisterNavigation());
        $this->assertTrue(OutputReviewResource::shouldRegisterNavigation());
    }

    public function test_the_qc_and_cost_groups_leave_the_production_lead_sidebar(): void
    {
        $productionLead = User::factory()->create();
        $productionLead->assignRole(UserRole::PRODUCTION_LEAD->value);

        $this->actingAs($productionLead);

        $groupLabels = array_map(
            fn (NavigationGroup $group): ?string => $group->getLabel(),
            Filament::getNavigation(),
        );

        $this->assertNotContains('Quality Control', $groupLabels);
        $this->assertNotContains('Payroll & Biaya', $groupLabels);

        $this->assertContains('Produksi', $groupLabels);
    }

    /**
     * Sisi sebaliknya dari aturan di atas: grupnya tetap utuh bagi peran yang
     * memang menjalankannya, termasuk Owner yang boleh membuka semuanya.
     */
    public function test_the_qc_and_cost_menus_stay_with_the_roles_that_run_them(): void
    {
        $qc = User::factory()->create();
        $qc->assignRole(UserRole::QC_INSPECTOR->value);

        $this->actingAs($qc);

        $this->assertTrue(QcOrderResource::shouldRegisterNavigation());
        $this->assertTrue(RepairResource::shouldRegisterNavigation());

        $finance = User::factory()->create();
        $finance->assignRole(UserRole::FINANCE_PAYROLL->value);

        $this->actingAs($finance);

        $this->assertTrue(RateCardResource::shouldRegisterNavigation());

        $owner = User::factory()->create();
        $owner->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $this->actingAs($owner);

        $this->assertTrue(QcOrderResource::shouldRegisterNavigation());
        $this->assertTrue(RepairResource::shouldRegisterNavigation());
        $this->assertTrue(RateCardResource::shouldRegisterNavigation());
    }

    /**
     * Sidebar Kepala Produksi hanya menyisakan menu yang ia kerjakan sendiri.
     *
     * Line Produksi dan Perakitan adalah pengaturan lantai, sedangkan pekerjaan
     * harian mandor ada di Antrean Produksi dan Output 30 Menit. Pengajuan PO
     * Gudang pun bukan menunya: PurchaseOrderPolicy hanya membuka PO bagi
     * gudang, Finance, dan Owner. Yang dilepas hanya pendaftaran menunya —
     * halamannya tetap dapat dibuka lewat URL.
     */
    public function test_the_production_lead_keeps_only_the_production_menus(): void
    {
        $productionLead = User::factory()->create();
        $productionLead->assignRole(UserRole::PRODUCTION_LEAD->value);

        $this->actingAs($productionLead);

        $this->assertFalse(ProductionLineResource::shouldRegisterNavigation());
        $this->assertFalse(AssemblyResource::shouldRegisterNavigation());
        $this->assertFalse(MyPurchaseOrderResource::shouldRegisterNavigation());

        $this->assertTrue(OutputReviewResource::shouldRegisterNavigation());
        $this->assertTrue(ApprovedSampleResource::shouldRegisterNavigation());
    }

    /**
     * Menu Pengajuan PO Gudang berjalan bersama peran yang memang bersinggungan
     * dengan PO — pengajunya dan kedua peninjau yang sama dengan
     * PurchaseOrderPolicy::canView().
     */
    public function test_the_po_menu_stays_with_the_warehouse_finance_and_the_owner(): void
    {
        foreach ([UserRole::WAREHOUSE, UserRole::FINANCE_PAYROLL, UserRole::OWNER_EXECUTIVE] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role->value);

            $this->actingAs($user);

            $this->assertTrue(
                MyPurchaseOrderResource::shouldRegisterNavigation(),
                "Menu Pengajuan PO Gudang hilang dari sidebar {$role->value}.",
            );
        }

        // Penjahit tidak mengajukan PO, jadi menunya tidak menempel di sidebar-nya.
        $tailor = User::factory()->create();
        $tailor->assignRole(UserRole::TAILOR_OPERATOR->value);

        $this->actingAs($tailor);

        $this->assertFalse(MyPurchaseOrderResource::shouldRegisterNavigation());
    }

    public function test_guest_cannot_see_any_resource_in_navigation(): void
    {
        $this->assertFalse(SampleResource::shouldRegisterNavigation());
        $this->assertFalse(ApprovedSampleResource::shouldRegisterNavigation());
        $this->assertFalse(ArticleResource::shouldRegisterNavigation());
        $this->assertFalse(OutputEntryResource::shouldRegisterNavigation());
        $this->assertFalse(OutputReviewResource::shouldRegisterNavigation());
    }
}
