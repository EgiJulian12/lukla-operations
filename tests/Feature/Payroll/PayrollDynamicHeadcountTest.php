<?php

namespace Tests\Feature\Payroll;

use App\Enums\RateType;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\Client;
use App\Models\Component;
use App\Models\Order;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\ProductionLine;
use App\Models\RateCard;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use App\Services\Production\ProductionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Skenario penerimaan #2 — headcount penjahit dinamis (FR-015, BR-006, NFR-001).
 *
 * Lima penjahit yang bergabung di tengah minggu harus mendapat upah borongan
 * masing-masing, tanpa batas jumlah penjahit yang dapat diproses.
 */
class PayrollDynamicHeadcountTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole(UserRole::PRODUCTION_LEAD->value);
    }

    public function test_five_tailors_joining_midweek_all_receive_piece_rate_pay(): void
    {
        $client = Client::factory()->create();
        $article = Article::factory()->create(['client_id' => $client->getKey()]);
        $component = Component::factory()->create(['article_id' => $article->getKey()]);
        $order = Order::factory()->create(['client_id' => $client->getKey()]);
        $line = ProductionLine::factory()->create();

        RateCard::factory()->create([
            'client_id' => $client->getKey(),
            'article_id' => $article->getKey(),
            'component_id' => $component->getKey(),
            'rate_type' => RateType::SEWING,
            'rate' => 1000,
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        $weekStart = now()->startOfWeek();

        $period = PayrollPeriod::factory()->create([
            'period_start' => $weekStart->toDateString(),
            'period_end' => $weekStart->copy()->addDays(6)->toDateString(),
            'meal_allowance_percent' => 10,
            'qc_bonus_rate_per_unit' => 0,
        ]);

        $production = app(ProductionService::class);
        $operators = User::factory()->count(5)->create();

        foreach ($operators as $index => $operator) {
            $operator->assignRole(UserRole::TAILOR_OPERATOR->value);

            $entry = $production->submitOutput(
                order: $order,
                line: $line,
                operator: $operator,
                component: $component,
                quantity: 100,
                intervalAt: $weekStart->copy()->addHours(8 + $index),
            );

            $production->verifyOutput($entry, $this->supervisor);
        }

        app(PayrollService::class)->calculate($period, []);

        $this->assertDatabaseCount('payroll_entries', 5);

        foreach ($operators as $operator) {
            $row = PayrollEntry::query()
                ->where('payroll_period_id', $period->getKey())
                ->where('user_id', $operator->getKey())
                ->firstOrFail();

            $this->assertSame(100, $row->total_pieces);
            $this->assertEquals(100000.0, (float) $row->sewing_amount);
            $this->assertEquals(10000.0, (float) $row->meal_allowance_amount);
            $this->assertEquals(110000.0, (float) $row->total_amount);
        }

        $this->assertEquals(500000.0, (float) $period->fresh()->total_sewing_cost);
    }

    public function test_output_that_is_not_verified_never_reaches_payroll(): void
    {
        $client = Client::factory()->create();
        $article = Article::factory()->create(['client_id' => $client->getKey()]);
        $component = Component::factory()->create(['article_id' => $article->getKey()]);
        $order = Order::factory()->create(['client_id' => $client->getKey()]);
        $line = ProductionLine::factory()->create();

        RateCard::factory()->create([
            'client_id' => $client->getKey(),
            'article_id' => $article->getKey(),
            'component_id' => $component->getKey(),
            'rate' => 1000,
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        $weekStart = now()->startOfWeek();
        $period = PayrollPeriod::factory()->create([
            'period_start' => $weekStart->toDateString(),
            'period_end' => $weekStart->copy()->addDays(6)->toDateString(),
            'meal_allowance_percent' => 0,
            'qc_bonus_rate_per_unit' => 0,
        ]);

        app(ProductionService::class)->submitOutput(
            order: $order,
            line: $line,
            operator: User::factory()->create(),
            component: $component,
            quantity: 100,
            intervalAt: $weekStart->copy()->addHours(8),
        );

        app(PayrollService::class)->calculate($period, []);

        $this->assertDatabaseCount('payroll_entries', 0);
        $this->assertEquals(0.0, (float) $period->fresh()->total_sewing_cost);
    }
}
