<?php

namespace Tests\Feature\Production;

use App\Enums\OutputStatus;
use App\Enums\UserRole;
use App\Exceptions\DuplicateOutputInterval;
use App\Exceptions\InvalidOutputState;
use App\Models\Component;
use App\Models\Order;
use App\Models\OutputEntry;
use App\Models\ProductionLine;
use App\Models\User;
use App\Services\Production\ProductionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Output 30 menit dan gerbang verifikasinya (FR-011, FR-012).
 *
 * Line pada catatan output bersifat opsional: penugasan penjahit ditetapkan per
 * komponen, sehingga hasil kerja tetap dapat dilaporkan oleh penjahit yang tidak
 * sedang terdaftar di line mana pun.
 */
class OutputVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_output_is_locked_once_verified(): void
    {
        $service = app(ProductionService::class);

        $entry = $service->submitOutput(
            order: Order::factory()->create(),
            line: ProductionLine::factory()->create(),
            operator: User::factory()->create(),
            component: Component::factory()->create(),
            quantity: 24,
            intervalAt: now()->setTime(8, 0),
        );

        $this->assertSame(OutputStatus::SUBMITTED, $entry->status);
        $this->assertFalse($entry->isPayrollEligible());

        $supervisor = User::factory()->create();
        $supervisor->assignRole(UserRole::PRODUCTION_LEAD->value);

        $verified = $service->verifyOutput($entry, $supervisor);

        $this->assertSame(OutputStatus::VERIFIED, $verified->status);
        $this->assertTrue($verified->isLocked());
        $this->assertTrue($verified->isPayrollEligible());
        $this->assertSame($supervisor->getKey(), $verified->verified_by);
    }

    public function test_an_already_verified_entry_cannot_be_verified_again(): void
    {
        $service = app(ProductionService::class);
        $supervisor = User::factory()->create();
        $supervisor->assignRole(UserRole::PRODUCTION_LEAD->value);

        $entry = OutputEntry::factory()->create();
        $service->verifyOutput($entry, $supervisor);

        $this->expectException(InvalidOutputState::class);

        $service->verifyOutput($entry->refresh(), $supervisor);
    }

    public function test_a_second_report_for_the_same_component_and_interval_is_rejected(): void
    {
        $service = app(ProductionService::class);
        $order = Order::factory()->create();
        $line = ProductionLine::factory()->create();
        $operator = User::factory()->create();
        $component = Component::factory()->create();

        $service->submitOutput($order, $line, $operator, $component, 10, Carbon::parse('2026-10-12 08:17'));

        $this->expectException(DuplicateOutputInterval::class);

        // Kedua laporan harus jatuh di interval 08:00–08:30 yang sama.
        $service->submitOutput($order, $line, $operator, $component, 12, Carbon::parse('2026-10-12 08:25'));
    }

    public function test_the_interval_is_rounded_to_the_enclosing_thirty_minutes(): void
    {
        $service = app(ProductionService::class);

        $entry = $service->submitOutput(
            order: Order::factory()->create(),
            line: ProductionLine::factory()->create(),
            operator: User::factory()->create(),
            component: Component::factory()->create(),
            quantity: 15,
            intervalAt: Carbon::parse('2026-10-12 08:17:45'),
        );

        $this->assertSame('08:00', $entry->interval_start->format('H:i'));
        $this->assertSame('08:30', $entry->interval_end->format('H:i'));
    }

    public function test_output_can_be_reported_without_a_production_line(): void
    {
        $service = app(ProductionService::class);

        $entry = $service->submitOutput(
            order: Order::factory()->create(),
            line: null,
            operator: User::factory()->create(),
            component: Component::factory()->create(),
            quantity: 18,
            intervalAt: Carbon::parse('2026-10-12 09:05'),
        );

        $this->assertNull($entry->production_line_id);
        $this->assertSame('09:00', $entry->interval_start->format('H:i'));
        $this->assertDatabaseHas('output_entries', [
            'id' => $entry->getKey(),
            'production_line_id' => null,
        ]);
    }

    public function test_assembly_refuses_output_that_is_not_verified(): void
    {
        $order = Order::factory()->create();
        $pending = OutputEntry::factory()->create([
            'order_id' => $order->getKey(),
            'client_id' => $order->client_id,
        ]);

        $this->expectException(InvalidOutputState::class);

        app(ProductionService::class)->assemble($order, [$pending->getKey()], 5);
    }

    public function test_assembly_links_only_verified_output_of_the_same_order(): void
    {
        $order = Order::factory()->create();

        $verified = OutputEntry::factory()->verified()->create([
            'order_id' => $order->getKey(),
            'client_id' => $order->client_id,
        ]);

        $assembly = app(ProductionService::class)->assemble($order, [$verified->getKey()], 20);

        $this->assertSame(20, $assembly->quantity);
        $this->assertSame($order->getKey(), $assembly->order_id);
        $this->assertTrue($assembly->outputEntries()->whereKey($verified->getKey())->exists());
    }
}
