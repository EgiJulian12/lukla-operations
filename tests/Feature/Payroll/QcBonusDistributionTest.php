<?php

namespace Tests\Feature\Payroll;

use App\Enums\QcResult;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\PayrollPeriod;
use App\Models\QcBonusPool;
use App\Models\QcRecord;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Skenario penerimaan #3 — pembagian bonus QC tiga arah (FR-017).
 */
class QcBonusDistributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_three_thousand_passed_units_split_the_pool_equally_to_three_recipients(): void
    {
        $weekStart = now()->startOfWeek();

        $period = PayrollPeriod::factory()->create([
            'period_start' => $weekStart->toDateString(),
            'period_end' => $weekStart->copy()->addDays(6)->toDateString(),
            'meal_allowance_percent' => 0,
            'qc_bonus_rate_per_unit' => 100,
        ]);

        foreach ([1, 2] as $dayOffset) {
            $order = Order::factory()->create();

            QcRecord::factory()->create([
                'order_id' => $order->getKey(),
                'client_id' => $order->client_id,
                'result' => QcResult::PASS,
                'quantity' => 1500,
                'inspected_at' => $weekStart->copy()->addDays($dayOffset),
            ]);
        }

        // Unit scrap tidak membentuk pool (FR-017).
        $scrapOrder = Order::factory()->create();
        QcRecord::factory()->scrapped()->create([
            'order_id' => $scrapOrder->getKey(),
            'client_id' => $scrapOrder->client_id,
            'quantity' => 400,
            'inspected_at' => $weekStart->copy()->addDay(),
        ]);

        $supervisors = User::factory()->count(2)->create();

        foreach ($supervisors as $supervisor) {
            $supervisor->assignRole(UserRole::PRODUCTION_LEAD->value);
        }

        $head = User::factory()->create();
        $head->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $recipients = [...$supervisors->pluck('id')->all(), $head->getKey()];

        app(PayrollService::class)->calculate($period, $recipients);

        $pool = QcBonusPool::query()->where('payroll_period_id', $period->getKey())->firstOrFail();

        $this->assertSame(3000, $pool->total_passed_units);
        $this->assertEquals(300000.0, (float) $pool->total_pool);
        $this->assertSame(3, $pool->recipient_count);
        $this->assertEquals(100000.0, (float) $pool->amount_per_recipient);

        $this->assertDatabaseCount('qc_bonus_distributions', 3);
        $this->assertEquals(300000.0, (float) $pool->distributions()->sum('amount'));

        foreach ($recipients as $userId) {
            $this->assertEquals(
                100000.0,
                (float) $pool->distributions()->where('user_id', $userId)->value('amount'),
            );
        }
    }

    public function test_the_default_recipients_are_two_supervisors_and_the_operations_head(): void
    {
        $weekStart = now()->startOfWeek();

        $period = PayrollPeriod::factory()->create([
            'period_start' => $weekStart->toDateString(),
            'period_end' => $weekStart->copy()->addDays(6)->toDateString(),
            'meal_allowance_percent' => 0,
            'qc_bonus_rate_per_unit' => 50,
        ]);

        $order = Order::factory()->create();

        QcRecord::factory()->create([
            'order_id' => $order->getKey(),
            'client_id' => $order->client_id,
            'result' => QcResult::PASS,
            'quantity' => 300,
            'inspected_at' => $weekStart->copy()->addDay(),
        ]);

        User::factory()->count(2)->create()
            ->each(fn (User $user) => $user->assignRole(UserRole::PRODUCTION_LEAD->value));

        User::factory()->create()->assignRole(UserRole::OWNER_EXECUTIVE->value);

        app(PayrollService::class)->calculate($period, []);

        $pool = QcBonusPool::query()->where('payroll_period_id', $period->getKey())->firstOrFail();

        $this->assertSame(3, $pool->recipient_count);
        $this->assertEquals(15000.0, (float) $pool->total_pool);
        $this->assertEquals(5000.0, (float) $pool->amount_per_recipient);
    }
}
