<?php

namespace Tests\Feature\Production;

use App\Enums\UserRole;
use App\Exceptions\UnassignedOperatorException;
use App\Models\LineAssignment;
use App\Models\ProductionLine;
use App\Models\User;
use App\Services\Production\LineAllocationService;
use App\Services\Production\ProductionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class LineAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    private LineAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->service = app(LineAllocationService::class);
    }

    public function test_operator_can_be_assigned_to_production_line_with_station_number(): void
    {
        $line = ProductionLine::factory()->create(['name' => 'Lini Jahit 1']);
        $operator = $this->createTailor('Budi');

        $assignment = $this->service->assignOperator($line, $operator, 'ST-01');

        $this->assertInstanceOf(LineAssignment::class, $assignment);
        $this->assertSame($line->getKey(), $assignment->production_line_id);
        $this->assertSame($operator->getKey(), $assignment->user_id);
        $this->assertSame('ST-01', $assignment->station_number);
        $this->assertNotNull($assignment->assigned_at);
        $this->assertNull($assignment->unassigned_at);
        $this->assertTrue($assignment->isActive());
    }

    public function test_line_capacity_is_dynamic_without_rigid_slot_limits(): void
    {
        $line = ProductionLine::factory()->create();
        $operators = User::factory()->count(10)->create();

        foreach ($operators as $index => $operator) {
            $operator->assignRole(UserRole::TAILOR_OPERATOR->value);
            $station = sprintf('ST-%02d', $index + 1);

            $assignment = $this->service->assignOperator($line, $operator, $station);
            $this->assertTrue($assignment->isActive());
        }

        $activeAssignments = $this->service->getActiveLineAssignments($line);
        $this->assertCount(10, $activeAssignments);
    }

    public function test_assigning_to_another_line_automatically_closes_previous_active_assignment(): void
    {
        $lineA = ProductionLine::factory()->create(['name' => 'Lini A']);
        $lineB = ProductionLine::factory()->create(['name' => 'Lini B']);
        $operator = $this->createTailor('Siti');

        // Pertama dialokasikan ke Lini A
        $assignmentA = $this->service->assignOperator($lineA, $operator, 'ST-01');
        $this->assertTrue($assignmentA->isActive());

        // Kemudian dirotasi ke Lini B
        $assignmentB = $this->service->assignOperator($lineB, $operator, 'ST-05');

        $assignmentA->refresh();
        $this->assertFalse($assignmentA->isActive());
        $this->assertNotNull($assignmentA->unassigned_at);

        $this->assertTrue($assignmentB->isActive());
        $this->assertSame($lineB->getKey(), $assignmentB->production_line_id);
        $this->assertSame('ST-05', $assignmentB->station_number);

        // Hanya 1 alokasi aktif untuk Siti
        $active = $this->service->getActiveAssignment($operator);
        $this->assertNotNull($active);
        $this->assertSame($assignmentB->getKey(), $active->getKey());
    }

    public function test_station_rotation_within_same_line_closes_old_and_creates_new_assignment(): void
    {
        $line = ProductionLine::factory()->create();
        $operator = $this->createTailor('Ahmad');

        $initialAssignment = $this->service->assignOperator($line, $operator, 'ST-01');

        // Rotasi ke stasiun baru di lini yang sama
        $newAssignment = $this->service->rotateStation($initialAssignment, 'ST-02');

        $initialAssignment->refresh();
        $this->assertFalse($initialAssignment->isActive());
        $this->assertNotNull($initialAssignment->unassigned_at);

        $this->assertTrue($newAssignment->isActive());
        $this->assertSame($line->getKey(), $newAssignment->production_line_id);
        $this->assertSame($operator->getKey(), $newAssignment->user_id);
        $this->assertSame('ST-02', $newAssignment->station_number);
        $this->assertNotSame($initialAssignment->getKey(), $newAssignment->getKey());

        // Kedua record tetap ada di database untuk audit trail (FR-022)
        $this->assertDatabaseCount('line_assignments', 2);
    }

    public function test_rotating_station_on_closed_assignment_throws_exception(): void
    {
        $line = ProductionLine::factory()->create();
        $operator = $this->createTailor('Dewi');

        $assignment = $this->service->assignOperator($line, $operator, 'ST-01');
        $this->service->unassignOperator($assignment);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sudah tidak aktif');

        $this->service->rotateStation($assignment, 'ST-03');
    }

    public function test_rotating_to_same_station_returns_current_assignment(): void
    {
        $line = ProductionLine::factory()->create();
        $operator = $this->createTailor('Eko');

        $assignment = $this->service->assignOperator($line, $operator, 'ST-01');

        $sameAssignment = $this->service->rotateStation($assignment, 'ST-01');

        $this->assertSame($assignment->getKey(), $sameAssignment->getKey());
        $this->assertTrue($sameAssignment->isActive());
        $this->assertDatabaseCount('line_assignments', 1);
    }

    public function test_unassign_operator_closes_assignment_without_deleting_record(): void
    {
        $line = ProductionLine::factory()->create();
        $operator = $this->createTailor('Fajar');

        $assignment = $this->service->assignOperator($line, $operator, 'ST-04');
        $closed = $this->service->unassignOperator($assignment);

        $this->assertFalse($closed->isActive());
        $this->assertNotNull($closed->unassigned_at);

        // Audit trail record tetap tersimpan
        $this->assertDatabaseHas('line_assignments', [
            'id' => $assignment->getKey(),
            'production_line_id' => $line->getKey(),
            'user_id' => $operator->getKey(),
            'station_number' => 'ST-04',
        ]);

        $this->assertNull($this->service->getActiveAssignment($operator));
    }

    public function test_get_active_line_assignments_returns_only_active_operators(): void
    {
        $line = ProductionLine::factory()->create();
        $activeOp1 = $this->createTailor('Op1');
        $activeOp2 = $this->createTailor('Op2');
        $inactiveOp = $this->createTailor('Op3');

        $this->service->assignOperator($line, $activeOp1, 'ST-01');
        $this->service->assignOperator($line, $activeOp2, 'ST-02');
        $inactiveAssign = $this->service->assignOperator($line, $inactiveOp, 'ST-03');
        $this->service->unassignOperator($inactiveAssign);

        $activeList = $this->service->getActiveLineAssignments($line);

        $this->assertCount(2, $activeList);
        $this->assertTrue($activeList->contains('user_id', $activeOp1->getKey()));
        $this->assertTrue($activeList->contains('user_id', $activeOp2->getKey()));
        $this->assertFalse($activeList->contains('user_id', $inactiveOp->getKey()));
    }

    public function test_assigning_non_tailor_user_throws_exception(): void
    {
        $line = ProductionLine::factory()->create();
        $clientUser = User::factory()->create();
        $clientUser->assignRole(UserRole::CLIENT_MAKLON->value);

        $this->expectException(UnassignedOperatorException::class);

        $this->service->assignOperator($line, $clientUser, 'ST-01');
    }

    public function test_production_service_delegates_to_line_allocation_service(): void
    {
        $line = ProductionLine::factory()->create();
        $operator = $this->createTailor('Hendra');
        $productionService = app(ProductionService::class);

        $assignment = $productionService->assignOperator($line, $operator, 'ST-09');

        $this->assertSame('ST-09', $assignment->station_number);
        $this->assertTrue($assignment->isActive());

        $closed = $productionService->unassignOperator($assignment);
        $this->assertFalse($closed->isActive());
    }

    private function createTailor(string $name = 'Penjahit'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole(UserRole::TAILOR_OPERATOR->value);

        return $user;
    }
}
