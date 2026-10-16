<?php

namespace Tests\Feature\Production;

use App\Enums\OrderStatus;
use App\Enums\OutputStatus;
use App\Enums\UserRole;
use App\Exceptions\DuplicateOutputInterval;
use App\Exceptions\UnassignedOperatorException;
use App\Models\Component;
use App\Models\Order;
use App\Models\OrderComponentAssignment;
use App\Models\ProductionLine;
use App\Models\User;
use App\Services\Production\ProductionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class OutputRecordingRulesTest extends TestCase
{
    use RefreshDatabase;

    private ProductionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->service = app(ProductionService::class);
    }

    public function test_calculate_interval_rounds_to_enclosing_thirty_minute_boundaries(): void
    {
        // 08:14 -> 08:00 to 08:30
        [$start1, $end1] = $this->service->calculateInterval(Carbon::parse('2026-10-15 08:14:25'));
        $this->assertSame('2026-10-15 08:00:00', $start1->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-15 08:30:00', $end1->format('Y-m-d H:i:s'));

        // 08:45 -> 08:30 to 09:00
        [$start2, $end2] = $this->service->calculateInterval(Carbon::parse('2026-10-15 08:45:10'));
        $this->assertSame('2026-10-15 08:30:00', $start2->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-15 09:00:00', $end2->format('Y-m-d H:i:s'));

        // Exact 08:00:00 -> 08:00 to 08:30
        [$start3, $end3] = $this->service->calculateInterval(Carbon::parse('2026-10-15 08:00:00'));
        $this->assertSame('2026-10-15 08:00:00', $start3->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-15 08:30:00', $end3->format('Y-m-d H:i:s'));

        // Exact 08:30:00 -> 08:30 to 09:00
        [$start4, $end4] = $this->service->calculateInterval(Carbon::parse('2026-10-15 08:30:00'));
        $this->assertSame('2026-10-15 08:30:00', $start4->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-15 09:00:00', $end4->format('Y-m-d H:i:s'));
    }

    public function test_submit_output_rejects_quantity_less_than_one(): void
    {
        $order = Order::factory()->create();
        $operator = $this->createTailor();
        $component = Component::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Jumlah output minimal satu unit.');

        $this->service->submitOutput(
            order: $order,
            line: null,
            operator: $operator,
            component: $component,
            quantity: 0,
            intervalAt: Carbon::parse('2026-10-15 08:15:00'),
        );
    }

    public function test_newly_submitted_output_is_initialized_with_submitted_status_and_not_payroll_eligible(): void
    {
        $order = Order::factory()->create();
        $operator = $this->createTailor();
        $component = Component::factory()->create();

        $entry = $this->service->submitOutput(
            order: $order,
            line: null,
            operator: $operator,
            component: $component,
            quantity: 15,
            intervalAt: Carbon::parse('2026-10-15 08:15:00'),
            notes: 'Batch pagi 15 pcs',
        );

        $this->assertSame(OutputStatus::SUBMITTED, $entry->status);
        $this->assertFalse($entry->isPayrollEligible());
        $this->assertFalse($entry->isLocked());
        $this->assertSame('Batch pagi 15 pcs', $entry->notes);
        $this->assertSame('2026-10-15 08:00:00', $entry->interval_start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-15 08:30:00', $entry->interval_end->format('Y-m-d H:i:s'));
    }

    public function test_duplicate_submission_in_same_thirty_minute_interval_is_prevented(): void
    {
        $order = Order::factory()->create();
        $operator = $this->createTailor();
        $component = Component::factory()->create();

        $this->service->submitOutput(
            order: $order,
            line: null,
            operator: $operator,
            component: $component,
            quantity: 10,
            intervalAt: Carbon::parse('2026-10-15 08:05:00'),
        );

        $this->expectException(DuplicateOutputInterval::class);

        // Same interval (08:00 - 08:30), same operator and component
        $this->service->submitOutput(
            order: $order,
            line: null,
            operator: $operator,
            component: $component,
            quantity: 12,
            intervalAt: Carbon::parse('2026-10-15 08:25:00'),
        );
    }

    public function test_output_can_be_submitted_without_a_production_line(): void
    {
        $order = Order::factory()->create();
        $operator = $this->createTailor();
        $component = Component::factory()->create();

        $entry = $this->service->submitOutput(
            order: $order,
            line: null,
            operator: $operator,
            component: $component,
            quantity: 20,
            intervalAt: Carbon::parse('2026-10-15 09:00:00'),
        );

        $this->assertNull($entry->production_line_id);
        $this->assertSame(20, $entry->quantity);
    }

    public function test_operator_with_active_line_assignment_can_submit_output(): void
    {
        $order = Order::factory()->create();
        $operator = $this->createTailor();
        $line = ProductionLine::factory()->create();
        $component = Component::factory()->create();

        // Assign operator to line
        $this->service->assignOperator($line, $operator);

        $entry = $this->service->submitOutput(
            order: $order,
            line: $line,
            operator: $operator,
            component: $component,
            quantity: 18,
            intervalAt: Carbon::parse('2026-10-15 09:10:00'),
        );

        $this->assertSame($line->getKey(), $entry->production_line_id);
        $this->assertSame(18, $entry->quantity);
    }

    public function test_operator_with_closed_line_assignment_is_rejected(): void
    {
        $order = Order::factory()->create();
        $operator = $this->createTailor();
        $line = ProductionLine::factory()->create();
        $component = Component::factory()->create();

        // Assign and then unassign operator
        $assignment = $this->service->assignOperator($line, $operator);
        $this->service->unassignOperator($assignment);

        $this->expectException(UnassignedOperatorException::class);

        $this->service->submitOutput(
            order: $order,
            line: $line,
            operator: $operator,
            component: $component,
            quantity: 10,
            intervalAt: Carbon::parse('2026-10-15 09:15:00'),
        );
    }

    public function test_operator_active_on_another_line_cannot_report_for_different_line(): void
    {
        $order = Order::factory()->create();
        $operator = $this->createTailor();
        $lineA = ProductionLine::factory()->create(['name' => 'Line A']);
        $lineB = ProductionLine::factory()->create(['name' => 'Line B']);
        $component = Component::factory()->create();

        // Active on Line A
        $this->service->assignOperator($lineA, $operator);

        $this->expectException(UnassignedOperatorException::class);

        // Try submitting for Line B
        $this->service->submitOutput(
            order: $order,
            line: $lineB,
            operator: $operator,
            component: $component,
            quantity: 10,
            intervalAt: Carbon::parse('2026-10-15 09:15:00'),
        );
    }

    public function test_tailor_assigned_to_component_can_submit_output(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::IN_PRODUCTION]);
        $operator = $this->createTailor();
        $component = Component::factory()->create();

        // Create component assignment
        OrderComponentAssignment::create([
            'order_id' => $order->getKey(),
            'component_id' => $component->getKey(),
            'user_id' => $operator->getKey(),
            'assigned_at' => now(),
        ]);

        $entry = $this->service->submitOutput(
            order: $order,
            line: null,
            operator: $operator,
            component: $component,
            quantity: 25,
            intervalAt: Carbon::parse('2026-10-15 10:00:00'),
        );

        $this->assertSame($component->getKey(), $entry->component_id);
        $this->assertSame(25, $entry->quantity);
    }

    public function test_tailor_not_assigned_to_component_is_rejected_when_order_has_component_plan(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::IN_PRODUCTION]);
        $assignedTailor = $this->createTailor();
        $unassignedTailor = $this->createTailor();
        $component = Component::factory()->create();

        // Component is assigned to assignedTailor, NOT unassignedTailor
        OrderComponentAssignment::create([
            'order_id' => $order->getKey(),
            'component_id' => $component->getKey(),
            'user_id' => $assignedTailor->getKey(),
            'assigned_at' => now(),
        ]);

        $this->expectException(UnassignedOperatorException::class);

        $this->service->submitOutput(
            order: $order,
            line: null,
            operator: $unassignedTailor,
            component: $component,
            quantity: 10,
            intervalAt: Carbon::parse('2026-10-15 10:00:00'),
        );
    }

    public function test_user_without_tailor_role_cannot_submit_output(): void
    {
        $order = Order::factory()->create();
        $nonTailor = User::factory()->create();
        $nonTailor->assignRole(UserRole::CLIENT_MAKLON->value);
        $component = Component::factory()->create();

        $this->expectException(UnassignedOperatorException::class);

        $this->service->submitOutput(
            order: $order,
            line: null,
            operator: $nonTailor,
            component: $component,
            quantity: 10,
            intervalAt: Carbon::parse('2026-10-15 10:00:00'),
        );
    }

    private function createTailor(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::TAILOR_OPERATOR->value);

        return $user;
    }
}
