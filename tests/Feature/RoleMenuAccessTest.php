<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\ApprovedSamples\ApprovedSampleResource;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\OutputEntries\OutputEntryResource;
use App\Filament\Resources\OutputReviews\OutputReviewResource;
use App\Filament\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\Resources\ProductionLines\ProductionLineResource;
use App\Filament\Resources\QcRecords\QcRecordResource;
use App\Filament\Resources\Samples\SampleResource;
use App\Models\User;
use Database\Seeders\RoleSeeder;
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
        $this->assertTrue(ProductionLineResource::shouldRegisterNavigation());
        $this->assertFalse(SampleResource::shouldRegisterNavigation());

        // Output 30 Menit milik Kepala Produksi berbariskan order dan tinggal di
        // menu Produksi; log datar OutputEntryResource tetap ada sebagai pintu
        // masuk penjahit, bukan sebagai menu mandor.
        $this->assertFalse(OutputEntryResource::shouldRegisterNavigation());
        $this->assertTrue(OutputReviewResource::canAccess());
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
