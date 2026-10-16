<?php

namespace Tests\Feature\Production;

use App\Enums\OutputStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidOutputState;
use App\Models\Component;
use App\Models\Order;
use App\Models\OutputEntry;
use App\Models\ProductionLine;
use App\Models\User;
use App\Services\Production\ProductionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Alur Verifikasi, Penolakan, dan Pengajuan Ulang Output (FR-012, BR 4.5).
 *
 * Supervisor berwenang memverifikasi atau menolak output dengan alasan yang jelas.
 * Penjahit dilarang memverifikasi atau menolak outputnya sendiri (separasi tugas).
 * Output yang ditolak dapat diajukan ulang (resubmit) oleh penjahit setelah perbaikan.
 */
class OutputResubmissionTest extends TestCase
{
    use RefreshDatabase;

    private ProductionService $service;

    private User $supervisor;

    private User $tailor;

    private Order $order;

    private Component $component;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->service = app(ProductionService::class);

        $this->supervisor = User::factory()->create(['name' => 'Mandor Budi']);
        $this->supervisor->assignRole(UserRole::PRODUCTION_LEAD->value);

        $this->tailor = User::factory()->create(['name' => 'Siti Penjahit']);
        $this->tailor->assignRole(UserRole::TAILOR_OPERATOR->value);

        $this->order = Order::factory()->create();
        $this->component = Component::factory()->create();
    }

    public function test_supervisor_with_production_lead_role_can_verify_submitted_output(): void
    {
        $entry = $this->createSubmittedOutput(20);

        $verified = $this->service->verifyOutput($entry, $this->supervisor);

        $this->assertSame(OutputStatus::VERIFIED, $verified->status);
        $this->assertSame($this->supervisor->getKey(), $verified->verified_by);
        $this->assertNotNull($verified->verified_at);
        $this->assertTrue($verified->isPayrollEligible());
        $this->assertTrue($verified->isLocked());
    }

    public function test_supervisor_with_production_lead_role_can_reject_submitted_output_with_reason(): void
    {
        $entry = $this->createSubmittedOutput(20);

        $rejected = $this->service->rejectOutput($entry, 'Jahitan kerah melintir 2mm', $this->supervisor);

        $this->assertSame(OutputStatus::REJECTED, $rejected->status);
        $this->assertSame('Jahitan kerah melintir 2mm', $rejected->rejection_reason);
        $this->assertSame($this->supervisor->getKey(), $rejected->rejected_by);
        $this->assertSame('Jahitan kerah melintir 2mm', $rejected->notes);
        $this->assertFalse($rejected->isPayrollEligible());
        $this->assertFalse($rejected->isLocked());
    }

    public function test_operator_cannot_verify_own_output(): void
    {
        $entry = $this->createSubmittedOutput(15);

        // Sekalipun penjahit diberi role PRODUCTION_LEAD, self-verification tetap dilarang.
        $this->tailor->assignRole(UserRole::PRODUCTION_LEAD->value);

        $this->expectException(InvalidOutputState::class);
        $this->expectExceptionMessage('Operator dilarang memverifikasi atau menolak output miliknya sendiri');

        $this->service->verifyOutput($entry, $this->tailor);
    }

    public function test_operator_cannot_reject_own_output(): void
    {
        $entry = $this->createSubmittedOutput(15);

        $this->tailor->assignRole(UserRole::PRODUCTION_LEAD->value);

        $this->expectException(InvalidOutputState::class);
        $this->expectExceptionMessage('Operator dilarang memverifikasi atau menolak output miliknya sendiri');

        $this->service->rejectOutput($entry, 'Ingin batalkan sendiri', $this->tailor);
    }

    public function test_user_without_production_lead_role_cannot_verify_output(): void
    {
        $entry = $this->createSubmittedOutput(15);
        $warehouseStaff = User::factory()->create();
        $warehouseStaff->assignRole(UserRole::WAREHOUSE->value);

        $this->expectException(InvalidOutputState::class);
        $this->expectExceptionMessage('Hanya Kepala Produksi atau Mandor (PRODUCTION_LEAD) yang berhak memverifikasi atau menolak output.');

        $this->service->verifyOutput($entry, $warehouseStaff);
    }

    public function test_user_without_production_lead_role_cannot_reject_output(): void
    {
        $entry = $this->createSubmittedOutput(15);
        $warehouseStaff = User::factory()->create();
        $warehouseStaff->assignRole(UserRole::WAREHOUSE->value);

        $this->expectException(InvalidOutputState::class);
        $this->expectExceptionMessage('Hanya Kepala Produksi atau Mandor (PRODUCTION_LEAD) yang berhak memverifikasi atau menolak output.');

        $this->service->rejectOutput($entry, 'Bukan wewenang staf gudang', $warehouseStaff);
    }

    public function test_tailor_can_resubmit_rejected_output_with_corrected_quantity_and_notes(): void
    {
        $entry = $this->createSubmittedOutput(20);
        $this->service->rejectOutput($entry, 'Ada 2 pcs cacat jahitan', $this->supervisor);

        $this->assertSame(OutputStatus::REJECTED, $entry->status);

        // Penjahit memperbaiki dan mengajukan ulang dengan kuantitas 18 pcs
        $resubmitted = $this->service->resubmitOutput($entry, 18, 'Sudah diperbaiki dan dipotong 2 pcs yang cacat');

        $this->assertSame(OutputStatus::SUBMITTED, $resubmitted->status);
        $this->assertSame(18, $resubmitted->quantity);
        $this->assertSame('Sudah diperbaiki dan dipotong 2 pcs yang cacat', $resubmitted->notes);

        // Record di database sudah berubah kembali menjadi SUBMITTED
        $this->assertDatabaseHas('output_entries', [
            'id' => $entry->getKey(),
            'status' => OutputStatus::SUBMITTED->value,
            'quantity' => 18,
            'notes' => 'Sudah diperbaiki dan dipotong 2 pcs yang cacat',
        ]);
    }

    public function test_output_that_is_not_rejected_cannot_be_resubmitted(): void
    {
        $entry = $this->createSubmittedOutput(20);

        $this->expectException(InvalidOutputState::class);
        $this->expectExceptionMessage('sehingga tidak dapat diajukan ulang.');

        $this->service->resubmitOutput($entry, 25);
    }

    public function test_resubmit_requires_quantity_of_at_least_one(): void
    {
        $entry = $this->createSubmittedOutput(20);
        $this->service->rejectOutput($entry, 'Semua cacat', $this->supervisor);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Jumlah output minimal satu unit.');

        $this->service->resubmitOutput($entry, 0);
    }

    public function test_resubmitted_output_can_subsequently_be_verified_by_supervisor(): void
    {
        $entry = $this->createSubmittedOutput(20);
        $this->service->rejectOutput($entry, 'Perbaiki sambungan', $this->supervisor);

        $resubmitted = $this->service->resubmitOutput($entry, 20, 'Sambungan sudah dirapikan');
        $verified = $this->service->verifyOutput($resubmitted, $this->supervisor);

        $this->assertSame(OutputStatus::VERIFIED, $verified->status);
        $this->assertSame($this->supervisor->getKey(), $verified->verified_by);
        $this->assertTrue($verified->isPayrollEligible());
    }

    private function createSubmittedOutput(int $quantity = 10): OutputEntry
    {
        return $this->service->submitOutput(
            order: $this->order,
            line: ProductionLine::factory()->create(),
            operator: $this->tailor,
            component: $this->component,
            quantity: $quantity,
            intervalAt: now()->setTime(8, 0),
        );
    }
}
