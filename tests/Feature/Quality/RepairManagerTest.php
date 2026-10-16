<?php

namespace Tests\Feature\Quality;

use App\Enums\QcResult;
use App\Enums\RepairStatus;
use App\Models\DefectCategory;
use App\Models\QcRecord;
use App\Models\Repair;
use App\Models\User;
use App\Services\Quality\RepairManager;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian domain repair: pembuatan tiket repair dan penyelesaiannya (FR-014, BR 4.6).
 */
class RepairManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_can_create_repair_ticket_from_qc_record_without_repairer(): void
    {
        $category = DefectCategory::factory()->create();
        $qcRecord = QcRecord::factory()->create([
            'result' => QcResult::REPAIR,
            'defect_category_id' => $category->getKey(),
            'notes' => 'Jahitan lepas di sisi kiri',
        ]);

        $manager = app(RepairManager::class);
        $repair = $manager->createFromQc($qcRecord);

        $this->assertInstanceOf(Repair::class, $repair);
        $this->assertSame($qcRecord->getKey(), $repair->qc_record_id);
        $this->assertNull($repair->assigned_repairer_id);
        $this->assertSame(RepairStatus::PENDING, $repair->status);
        $this->assertNull($repair->completed_at);
        $this->assertSame('Jahitan lepas di sisi kiri', $repair->notes);
    }

    public function test_can_create_repair_ticket_with_assigned_repairer(): void
    {
        $category = DefectCategory::factory()->create();
        $qcRecord = QcRecord::factory()->create([
            'result' => QcResult::REPAIR,
            'defect_category_id' => $category->getKey(),
        ]);
        $repairer = User::factory()->create();

        $manager = app(RepairManager::class);
        $repair = $manager->createFromQc($qcRecord, $repairer);

        $this->assertSame($repairer->getKey(), $repair->assigned_repairer_id);
        $this->assertSame(RepairStatus::IN_REPAIR, $repair->status);
    }

    public function test_can_mark_repair_as_completed(): void
    {
        $repair = Repair::factory()->create([
            'status' => RepairStatus::PENDING,
            'completed_at' => null,
        ]);

        $manager = app(RepairManager::class);
        $completedRepair = $manager->markCompleted($repair);

        $this->assertSame(RepairStatus::COMPLETED, $completedRepair->status);
        $this->assertNotNull($completedRepair->completed_at);
        $this->assertDatabaseHas('repairs', [
            'id' => $repair->id,
            'status' => RepairStatus::COMPLETED->value,
        ]);
    }
}
