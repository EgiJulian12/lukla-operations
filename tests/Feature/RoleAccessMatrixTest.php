<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\SampleStatus;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\Assembly;
use App\Models\Client;
use App\Models\Component;
use App\Models\DefectCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\Order;
use App\Models\OutputEntry;
use App\Models\PayrollPeriod;
use App\Models\ProductionLine;
use App\Models\PurchaseOrder;
use App\Models\QcRecord;
use App\Models\RateCard;
use App\Models\Repair;
use App\Models\Sample;
use App\Models\SampleRevision;
use App\Models\User;
use App\Models\WasteRecord;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matriks RBAC: batas wewenang antar peran (FR-021, FR-023, FR-025).
 *
 * Yang diuji di sini adalah pemisahan tugas, bukan sekadar "siapa boleh":
 * penjahit tidak memverifikasi pekerjaannya sendiri, produksi tidak menyentuh
 * penggajian, QC tidak menyentuh order, dan klien tidak melihat data klien lain.
 */
class RoleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_rate_cards_belong_to_production_finance_and_the_owner(): void
    {
        $this->assertTrue($this->userWithRole(UserRole::FINANCE_PAYROLL)->can('create', RateCard::class));
        $this->assertTrue($this->userWithRole(UserRole::OWNER_EXECUTIVE)->can('create', RateCard::class));
        $this->assertTrue($this->userWithRole(UserRole::PRODUCTION_LEAD)->can('create', RateCard::class));
        $this->assertFalse($this->userWithRole(UserRole::WAREHOUSE)->can('create', RateCard::class));
    }

    public function test_a_tailor_cannot_verify_their_own_output(): void
    {
        $entry = OutputEntry::factory()->create();

        $this->assertTrue($this->userWithRole(UserRole::PRODUCTION_LEAD)->can('verify', $entry));
        $this->assertFalse($this->userWithRole(UserRole::TAILOR_OPERATOR)->can('verify', $entry));
        $this->assertFalse($this->userWithRole(UserRole::QC_INSPECTOR)->can('verify', $entry));
    }

    public function test_only_qc_may_record_an_inspection(): void
    {
        $this->assertTrue($this->userWithRole(UserRole::QC_INSPECTOR)->can('create', QcRecord::class));
        $this->assertFalse($this->userWithRole(UserRole::WAREHOUSE)->can('create', QcRecord::class));
        $this->assertFalse($this->userWithRole(UserRole::PRODUCTION_LEAD)->can('create', QcRecord::class));
    }

    public function test_qc_inspector_can_only_view_qc_and_repair_menus(): void
    {
        $qcUser = $this->userWithRole(UserRole::QC_INSPECTOR);

        // Hanya boleh mengakses menu murni QC (Inspeksi QC & Perbaikan):
        $this->assertTrue($qcUser->can('viewAny', QcRecord::class));
        $this->assertTrue($qcUser->can('viewAny', Repair::class));

        // Tidak boleh mengakses menu perakitan dan kategori cacat (bukan bagian flow harian QC):
        $this->assertFalse($qcUser->can('viewAny', Assembly::class));
        $this->assertFalse($qcUser->can('viewAny', DefectCategory::class));

        // Tidak boleh mengakses resource lainnya di sidebar/panel:
        $this->assertFalse($qcUser->can('viewAny', Order::class));
        $this->assertFalse($qcUser->can('viewAny', Article::class));
        $this->assertFalse($qcUser->can('viewAny', Client::class));
        $this->assertFalse($qcUser->can('viewAny', Component::class));
        $this->assertFalse($qcUser->can('viewAny', Material::class));
        $this->assertFalse($qcUser->can('viewAny', InventoryItem::class));
        $this->assertFalse($qcUser->can('viewAny', InventoryMovement::class));
        $this->assertFalse($qcUser->can('viewAny', ProductionLine::class));
        $this->assertFalse($qcUser->can('viewAny', OutputEntry::class));
        $this->assertFalse($qcUser->can('viewAny', RateCard::class));
        $this->assertFalse($qcUser->can('viewAny', Sample::class));
        $this->assertFalse($qcUser->can('viewAny', SampleRevision::class));
        $this->assertFalse($qcUser->can('viewAny', WasteRecord::class));
        $this->assertFalse($qcUser->can('viewAny', PayrollPeriod::class));
        $this->assertFalse($qcUser->can('viewAny', Invoice::class));
        $this->assertFalse($qcUser->can('viewAny', PurchaseOrder::class));
    }

    public function test_warehouse_sidebar_navigation_contains_only_warehouse_menus(): void
    {
        $warehouseUser = $this->userWithRole(UserRole::WAREHOUSE);

        // Login sebagai user gudang
        $this->actingAs($warehouseUser);

        // Ambil navigasi panel Filament
        $navigation = Filament::getNavigation();

        // Ambil semua label grup yang muncul
        $groupLabels = array_map(fn ($group) => $group->getLabel(), $navigation);

        // Grup yang muncul HANYA Gudang & Cutting dan Master Data
        $this->assertContains('Gudang & Cutting', $groupLabels);
        $this->assertContains('Master Data', $groupLabels);

        // Grup lain TIDAK BOLEH muncul
        $this->assertNotContains('Quality Control', $groupLabels);
        $this->assertNotContains('Keuangan & Penggajian', $groupLabels);
        $this->assertNotContains('Produksi', $groupLabels);
        $this->assertNotContains('Pengembangan Produk', $groupLabels);
    }

    public function test_payroll_is_visible_only_to_finance_and_the_owner(): void
    {
        $this->assertTrue($this->userWithRole(UserRole::FINANCE_PAYROLL)->can('viewAny', PayrollPeriod::class));
        $this->assertTrue($this->userWithRole(UserRole::OWNER_EXECUTIVE)->can('viewAny', PayrollPeriod::class));
        $this->assertFalse($this->userWithRole(UserRole::QC_INSPECTOR)->can('viewAny', PayrollPeriod::class));
        $this->assertFalse($this->userWithRole(UserRole::TAILOR_OPERATOR)->can('viewAny', PayrollPeriod::class));
    }

    public function test_only_the_owner_turns_an_approved_sample_into_a_mass_order(): void
    {
        $sample = Sample::factory()->create();
        $revision = SampleRevision::factory()->create([
            'sample_id' => $sample->getKey(),
            'client_id' => $sample->client_id,
            'version' => 1,
        ]);

        $sample->update([
            'status' => SampleStatus::APPROVED,
            'approved_revision_id' => $revision->getKey(),
        ]);

        // Tim Sample menyiapkan revisinya, tetapi konversi ke order massal
        // dipegang Owner — lihat SamplePolicy::convertToOrder().
        $this->assertTrue($this->userWithRole(UserRole::OWNER_EXECUTIVE)->can('convertToOrder', $sample->refresh()));
        $this->assertFalse($this->userWithRole(UserRole::SAMPLE_TEAM)->can('convertToOrder', $sample->refresh()));
        $this->assertFalse($this->userWithRole(UserRole::WAREHOUSE)->can('convertToOrder', $sample->refresh()));
    }

    public function test_the_client_approves_the_sample_and_internal_staff_do_not(): void
    {
        $sample = Sample::factory()->create();

        $client = User::factory()->create(['client_id' => $sample->client_id]);
        $client->assignRole(UserRole::CLIENT_MAKLON->value);

        $this->assertTrue($client->can('approve', $sample));
        $this->assertTrue($client->can('reject', $sample));

        // Pemisahan tugas: persetujuan sample adalah ACC klien atas hasil
        // evaluasi fisik pakaian, bukan keputusan internal (FR-003, FR-026).
        $this->assertFalse($this->userWithRole(UserRole::OWNER_EXECUTIVE)->can('approve', $sample));
        $this->assertFalse($this->userWithRole(UserRole::SAMPLE_TEAM)->can('approve', $sample));
        $this->assertFalse($this->userWithRole(UserRole::WAREHOUSE)->can('approve', $sample));

        // Klien hanya memutuskan sample miliknya sendiri (NFR-004).
        $otherClient = User::factory()->create(['client_id' => Client::factory()->create()->getKey()]);
        $otherClient->assignRole(UserRole::CLIENT_MAKLON->value);

        $this->assertFalse($otherClient->can('approve', $sample));
    }

    public function test_purchase_orders_and_invoices_belong_to_finance(): void
    {
        $this->assertTrue($this->userWithRole(UserRole::FINANCE_PAYROLL)->can('create', PurchaseOrder::class));
        $this->assertTrue($this->userWithRole(UserRole::FINANCE_PAYROLL)->can('create', Invoice::class));
        $this->assertFalse($this->userWithRole(UserRole::PRODUCTION_LEAD)->can('create', PurchaseOrder::class));
        $this->assertFalse($this->userWithRole(UserRole::WAREHOUSE)->can('create', Invoice::class));

        // Owner melihat dokumen keuangan tanpa ikut menerbitkannya.
        $this->assertTrue($this->userWithRole(UserRole::OWNER_EXECUTIVE)->can('viewAny', Invoice::class));
        $this->assertFalse($this->userWithRole(UserRole::OWNER_EXECUTIVE)->can('create', Invoice::class));
    }

    public function test_a_client_account_sees_only_its_own_orders(): void
    {
        $mine = Client::factory()->create();
        $other = Client::factory()->create();

        Order::factory()->create(['client_id' => $mine->getKey()]);
        Order::factory()->create(['client_id' => $other->getKey()]);

        $user = User::factory()->create(['client_id' => $mine->getKey()]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        $this->actingAs($user);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame($mine->getKey(), Order::query()->firstOrFail()->client_id);
    }

    public function test_a_client_account_may_enter_the_portal_but_not_the_admin_panel(): void
    {
        $user = User::factory()->create(['client_id' => Client::factory()->create()->getKey()]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('portal')));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_an_internal_account_may_enter_the_admin_panel_but_not_the_portal(): void
    {
        $user = $this->userWithRole(UserRole::PRODUCTION_LEAD);

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('portal')));
    }

    public function test_a_completed_order_is_locked_against_edits(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::COMPLETED]);

        $this->assertFalse($this->userWithRole(UserRole::SAMPLE_TEAM)->can('update', $order));
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
