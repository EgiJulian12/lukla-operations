<?php

namespace Tests\Feature\Quality;

use App\Enums\QcResult;
use App\Enums\RepairStatus;
use App\Enums\UserRole;
use App\Models\Assembly;
use App\Models\DefectCategory;
use App\Models\Order;
use App\Models\Repair;
use App\Models\User;
use App\Models\WasteRecord;
use App\Services\Quality\QcService;
use App\Services\Quality\RepairManager;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian alur kerja end-to-end QC, Perakitan, dan Perbaikan (Repair).
 *
 * Alur:
 * 1. Perakitan baru masuk ke antrean barang siap dicek.
 * 2. QC memeriksa: jika PASS maka selesai & keluar dari antrean.
 * 3. Jika REPAIR: tiket dibuat di modul perbaikan, dipantau QC & Kepala Produksi.
 * 4. Kepala Produksi melakukan perbaikan dan menandai siap dicek ulang.
 * 5. QC memeriksa ulang hingga lolos (VERIFIED).
 * 6. Jika SCRAP: dialihkan ke limbah/waste_records.
 */
class QcWorkflowEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_assembled_goods_enter_pending_qc_queue_and_leave_when_passed(): void
    {
        $order = Order::factory()->create();
        $assembly = Assembly::factory()->create([
            'order_id' => $order->getKey(),
            'client_id' => $order->client_id,
            'quantity' => 10,
        ]);

        // Masuk daftar antrean siap dicek
        $this->assertSame(1, Assembly::pendingQc()->count());
        $this->assertSame(10, $assembly->remainingQuantity());
        $this->assertFalse($assembly->isFullyInspected());

        // QC memeriksa sebagian (6 unit) LOLOS
        $qcService = app(QcService::class);
        $qcService->recordInspection(
            assembly: $assembly,
            result: QcResult::PASS,
            quantity: 6,
        );

        $this->assertSame(4, $assembly->fresh()->remainingQuantity());
        $this->assertSame(1, Assembly::pendingQc()->count());

        // QC memeriksa sisa (4 unit) LOLOS
        $qcService->recordInspection(
            assembly: $assembly,
            result: QcResult::PASS,
            quantity: 4,
        );

        // Setelah seluruhnya lolos, otomatis keluar dari antrean pengecekan
        $this->assertSame(0, $assembly->fresh()->remainingQuantity());
        $this->assertTrue($assembly->fresh()->isFullyInspected());
        $this->assertSame(0, Assembly::pendingQc()->count());
    }

    public function test_qc_repair_creates_ticket_and_production_lead_can_repair_and_reinspect(): void
    {
        $order = Order::factory()->create();
        $assembly = Assembly::factory()->create([
            'order_id' => $order->getKey(),
            'client_id' => $order->client_id,
            'quantity' => 5,
        ]);
        $category = DefectCategory::factory()->create(['name' => 'Jahitan Miring']);
        $qcInspector = $this->userWithRole(UserRole::QC_INSPECTOR);
        $productionLead = $this->userWithRole(UserRole::PRODUCTION_LEAD);
        $tailor = $this->userWithRole(UserRole::TAILOR_OPERATOR);

        // QC memeriksa dan menemukan cacat REPAIR
        $qcService = app(QcService::class);
        $qcRecord = $qcService->recordInspection(
            assembly: $assembly,
            result: QcResult::REPAIR,
            quantity: 2,
            defectCategory: $category,
            notes: 'Jahitan samping miring 2 cm',
            inspector: $qcInspector,
        );

        // Tiket perbaikan terbuat
        $repair = Repair::query()->where('qc_record_id', $qcRecord->getKey())->firstOrFail();
        $this->assertSame(RepairStatus::PENDING, $repair->status);

        // Keduanya (QC dan Kepala Produksi) berwenang melihat tiket ini
        $this->assertTrue($qcInspector->can('view', $repair));
        $this->assertTrue($productionLead->can('view', $repair));

        // Kepala Produksi menugaskan penjahit untuk memperbaiki
        $repairManager = app(RepairManager::class);
        $repairManager->assignRepairer($repair, $tailor, 'Bongkar dan jahit ulang sisi kiri');

        $this->assertSame(RepairStatus::IN_REPAIR, $repair->fresh()->status);
        $this->assertSame($tailor->getKey(), $repair->fresh()->assigned_repairer_id);

        // Kepala Produksi menandai perbaikan selesai -> status Siap Dicek Ulang
        $repairManager->markCompleted($repair, 'Selesai dijahit rapi');

        $this->assertSame(RepairStatus::COMPLETED, $repair->fresh()->status);
        $this->assertNotNull($repair->fresh()->completed_at);

        // QC memeriksa ulang dan menyatakan LOLOS
        $qcService->recordInspection(
            assembly: $assembly,
            result: QcResult::PASS,
            quantity: 2,
            notes: 'Lolos QC Ulang setelah perbaikan',
            inspector: $qcInspector,
        );
        $repairManager->markVerified($repair);

        $this->assertSame(RepairStatus::VERIFIED, $repair->fresh()->status);
    }

    public function test_qc_scrap_creates_waste_record(): void
    {
        $order = Order::factory()->create();
        $assembly = Assembly::factory()->create([
            'order_id' => $order->getKey(),
            'client_id' => $order->client_id,
            'quantity' => 3,
        ]);
        $category = DefectCategory::factory()->create(['name' => 'Kain Sobek Parah']);

        $qcService = app(QcService::class);
        $qcService->recordInspection(
            assembly: $assembly,
            result: QcResult::SCRAP,
            quantity: 1,
            defectCategory: $category,
            notes: 'Sobek di bagian dada tidak bisa diperbaiki',
        );

        // Otomatis tercatat di WasteRecord
        $this->assertDatabaseHas('waste_records', [
            'order_id' => $order->getKey(),
            'quantity' => 1,
            'category' => 'Kain Sobek Parah',
        ]);
    }

    public function test_order_qc_summary_metrics_update_accurately_as_inspections_occur(): void
    {
        $order = Order::factory()->create(['quantity' => 100]);
        $assembly = Assembly::factory()->create([
            'order_id' => $order->getKey(),
            'client_id' => $order->client_id,
            'quantity' => 60,
        ]);
        $category = DefectCategory::factory()->create(['name' => 'Kancing Lepas']);
        $qcService = app(QcService::class);

        // Sebelum inspeksi
        $this->assertSame(60, $order->fresh()->qcReadyQuantity());
        $this->assertSame(0, $order->fresh()->qcPassedQuantity());
        $this->assertSame(0, $order->fresh()->qcRepairQuantity());
        $this->assertSame(0, $order->fresh()->qcScrapQuantity());
        $this->assertEquals(0.0, $order->fresh()->qcProgressPercentage());
        $this->assertTrue($order->fresh()->hasReadyQc());

        // Lolos 50 pcs
        $qcService->recordInspection(
            assembly: $assembly,
            result: QcResult::PASS,
            quantity: 50,
        );

        // Repair 7 pcs
        $qcService->recordInspection(
            assembly: $assembly,
            result: QcResult::REPAIR,
            quantity: 7,
            defectCategory: $category,
        );

        // Scrap 3 pcs
        $qcService->recordInspection(
            assembly: $assembly,
            result: QcResult::SCRAP,
            quantity: 3,
            defectCategory: $category,
        );

        $freshOrder = $order->fresh();
        // Sisa unit siap QC dari perakitan ini sudah habis (60 - (50+7+3) = 0)
        $this->assertSame(0, $freshOrder->qcReadyQuantity());
        $this->assertSame(50, $freshOrder->qcPassedQuantity());
        $this->assertSame(7, $freshOrder->qcRepairQuantity());
        $this->assertSame(3, $freshOrder->qcScrapQuantity());
        $this->assertEquals(50.0, $freshOrder->qcProgressPercentage());
        $this->assertFalse($freshOrder->hasReadyQc());
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
