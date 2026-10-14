<?php

namespace Tests\Feature\Quality;

use App\Enums\UserRole;
use App\Filament\Resources\QcRecords\Pages\ListQcRecords;
use App\Models\Order;
use App\Models\QcRecord;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QcFilamentPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_qc_inspector_can_access_qc_records_and_repairs_pages(): void
    {
        $qcUser = User::factory()->create();
        $qcUser->assignRole(UserRole::QC_INSPECTOR->value);

        $this->actingAs($qcUser);

        // Halaman Antrean Pesanan QC harus render dengan sukses (200 OK)
        $response = $this->get('/admin/qc-orders');
        $response->assertSuccessful();

        // Halaman QC Records harus render dengan sukses (200 OK)
        $response = $this->get('/admin/qc-records');
        $response->assertSuccessful();

        // Halaman Repairs harus render dengan sukses (200 OK)
        $response = $this->get('/admin/repairs');
        $response->assertSuccessful();

        // Akses ke /admin/rejected-items otomatis dilebur & di-redirect ke tab scrap QC Records
        $response = $this->get('/admin/rejected-items');
        $response->assertRedirect(url('/admin/qc-records?activeTab=scrap'));

        // Halaman Assemblies dan DefectCategories harus 403 Forbidden untuk QC
        $this->get('/admin/assemblies')->assertForbidden();
        $this->get('/admin/defect-categories')->assertForbidden();
    }

    public function test_production_lead_can_access_repairs_and_assemblies_pages(): void
    {
        $productionLead = User::factory()->create();
        $productionLead->assignRole(UserRole::PRODUCTION_LEAD->value);

        $this->actingAs($productionLead);

        $this->get('/admin/qc-orders')->assertSuccessful();
        $this->get('/admin/repairs')->assertSuccessful();
        $this->get('/admin/rejected-items')->assertRedirect(url('/admin/qc-records?activeTab=scrap'));
        $this->get('/admin/assemblies')->assertSuccessful();
        $this->get('/admin/defect-categories')->assertSuccessful();
    }

    public function test_qc_records_log_page_defers_data_until_filtered_or_explicitly_requested(): void
    {
        $qcUser = User::factory()->create();
        $qcUser->assignRole(UserRole::QC_INSPECTOR->value);

        $order = Order::factory()->create(['order_number' => 'ORD-FILTER-TEST']);
        $record = QcRecord::factory()->create([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'notes' => 'Catatan-Inspeksi-Unik-12345',
        ]);

        $this->actingAs($qcUser);

        // Tanpa filter: Jangan tampilkan data, tampilkan empty state instruksi filter
        $response = $this->get('/admin/qc-records');
        $response->assertSuccessful();
        $response->assertSee('Pilih Filter untuk Menampilkan Log QC');
        $response->assertDontSee('Catatan-Inspeksi-Unik-12345');

        // Dengan filter order: Tampilkan data
        $responseFiltered = $this->get("/admin/qc-records?tableFilters[order_id][value]={$order->id}");
        $responseFiltered->assertSuccessful();
        $responseFiltered->assertSee('ORD-FILTER-TEST');

        // Dengan toggle Tampilkan Semua: Tampilkan data
        $responseShowAll = $this->get('/admin/qc-records?tableFilters[show_all][isActive]=1');
        $responseShowAll->assertSuccessful();
        $responseShowAll->assertSee('ORD-FILTER-TEST');

        // Dengan filter tanggal per hari: Tampilkan data
        $today = now()->format('Y-m-d');
        $responseDate = $this->get("/admin/qc-records?tableFilters[inspected_date][date]={$today}");
        $responseDate->assertSuccessful();
        $responseDate->assertSee('ORD-FILTER-TEST');

        // Pengujian langsung aksi Livewire: tombol Tampilkan Semua & Reset Filter
        Livewire::test(ListQcRecords::class)
            ->assertDontSee('Catatan-Inspeksi-Unik-12345')
            ->call('toggleShowAll')
            ->assertSee('ORD-FILTER-TEST')
            ->call('resetTableFiltersForm')
            ->assertDontSee('Catatan-Inspeksi-Unik-12345');
    }
}
