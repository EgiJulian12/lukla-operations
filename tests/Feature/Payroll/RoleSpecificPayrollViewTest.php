<?php

namespace Tests\Feature\Payroll;

use App\Enums\OutputStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\QcResult;
use App\Enums\UserRole;
use App\Filament\Resources\QcBonuses\QcBonusResource;
use App\Filament\Resources\TailorWages\TailorWageResource;
use App\Models\Article;
use App\Models\Client;
use App\Models\Component;
use App\Models\Order;
use App\Models\OutputEntry;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\QcBonusDistribution;
use App\Models\QcBonusPool;
use App\Models\QcRecord;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleSpecificPayrollViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_qc_inspector_can_access_and_see_qc_bonus(): void
    {
        $qcUser = User::factory()->create();
        $qcUser->assignRole(UserRole::QC_INSPECTOR->value);

        $period = PayrollPeriod::factory()->create([
            'status' => PayrollPeriodStatus::CALCULATED,
            'qc_bonus_rate_per_unit' => 500,
        ]);

        $pool = QcBonusPool::factory()->create([
            'payroll_period_id' => $period->id,
            'total_passed_units' => 1000,
            'rate_per_unit' => 500,
            'total_pool' => 500000,
            'recipient_count' => 1,
            'amount_per_recipient' => 500000,
        ]);

        QcBonusDistribution::create([
            'qc_bonus_pool_id' => $pool->id,
            'user_id' => $qcUser->id,
            'amount' => 500000,
        ]);

        $this->actingAs($qcUser);

        $this->assertTrue(QcBonusResource::canAccess());
        $this->assertFalse(TailorWageResource::canAccess());

        $this->get(QcBonusResource::getUrl())
            ->assertOk();
    }

    public function test_tailor_can_only_see_their_own_wages(): void
    {
        $tailor1 = User::factory()->create();
        $tailor1->assignRole(UserRole::TAILOR_OPERATOR->value);

        $tailor2 = User::factory()->create();
        $tailor2->assignRole(UserRole::TAILOR_OPERATOR->value);

        $period = PayrollPeriod::factory()->create([
            'status' => PayrollPeriodStatus::CALCULATED,
        ]);

        $entry1 = PayrollEntry::factory()->create([
            'payroll_period_id' => $period->id,
            'user_id' => $tailor1->id,
            'total_pieces' => 150,
            'sewing_amount' => 750000,
            'meal_allowance_amount' => 100000,
            'total_amount' => 850000,
        ]);

        $entry2 = PayrollEntry::factory()->create([
            'payroll_period_id' => $period->id,
            'user_id' => $tailor2->id,
            'total_pieces' => 200,
            'sewing_amount' => 1000000,
            'meal_allowance_amount' => 100000,
            'total_amount' => 1100000,
        ]);

        $this->actingAs($tailor1);

        $this->assertTrue(TailorWageResource::canAccess());
        $this->assertFalse(QcBonusResource::canAccess());

        $query = TailorWageResource::getEloquentQuery();

        $this->assertSame(1, $query->count());
        $this->assertSame($entry1->id, $query->first()->id);

        $this->get(TailorWageResource::getUrl())
            ->assertOk();
    }

    public function test_tailor_wage_breakdown_resolves_projects_and_components(): void
    {
        $tailor = User::factory()->create();
        $tailor->assignRole(UserRole::TAILOR_OPERATOR->value);

        $client = Client::factory()->create(['name' => 'PT Brand Hebat']);
        $article = Article::factory()->create(['name' => 'Kemeja Formal']);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'article_id' => $article->id,
            'order_number' => 'ORD-TEST-001',
        ]);
        $component = Component::factory()->create([
            'article_id' => $article->id,
            'name' => 'Kerah',
        ]);

        $period = PayrollPeriod::factory()->create([
            'period_start' => now()->startOfWeek(),
            'period_end' => now()->endOfWeek(),
            'status' => PayrollPeriodStatus::CALCULATED,
        ]);

        $entry = PayrollEntry::factory()->create([
            'payroll_period_id' => $period->id,
            'user_id' => $tailor->id,
            'total_pieces' => 50,
        ]);

        OutputEntry::factory()->create([
            'client_id' => $client->id,
            'order_id' => $order->id,
            'component_id' => $component->id,
            'user_id' => $tailor->id,
            'quantity' => 50,
            'status' => OutputStatus::VERIFIED,
            'interval_start' => now()->startOfWeek()->addDay(),
            'interval_end' => now()->startOfWeek()->addDay()->addMinutes(30),
        ]);

        $breakdown = $entry->getGroupedWorkBreakdown();

        $this->assertCount(1, $breakdown);
        $this->assertSame('PT Brand Hebat', $breakdown[0]['client_name']);
        $this->assertSame('ORD-TEST-001', $breakdown[0]['order_number']);
        $this->assertSame('Kemeja Formal', $breakdown[0]['article_name']);
        $this->assertSame('Kerah', $breakdown[0]['component_name']);
        $this->assertSame(50, $breakdown[0]['total_pieces']);

        $this->actingAs($tailor);
        $this->get(TailorWageResource::getUrl())
            ->assertOk()
            ->assertSee('PT Brand Hebat')
            ->assertSee('Kerah');
    }

    public function test_qc_bonus_breakdown_resolves_projects_and_articles(): void
    {
        $qcUser = User::factory()->create();
        $qcUser->assignRole(UserRole::QC_INSPECTOR->value);

        $client = Client::factory()->create(['name' => 'CV Sukses Abadi']);
        $article = Article::factory()->create(['name' => 'Jaket Bomber']);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'article_id' => $article->id,
            'order_number' => 'ORD-QC-999',
        ]);

        $period = PayrollPeriod::factory()->create([
            'period_start' => now()->startOfWeek(),
            'period_end' => now()->endOfWeek(),
            'status' => PayrollPeriodStatus::CALCULATED,
            'qc_bonus_rate_per_unit' => 1000,
        ]);

        QcRecord::factory()->create([
            'client_id' => $client->id,
            'order_id' => $order->id,
            'result' => QcResult::PASS,
            'quantity' => 120,
            'inspected_at' => now()->startOfWeek()->addDays(2),
        ]);

        $breakdown = $period->getGroupedQcPassedBreakdown();

        $this->assertCount(1, $breakdown);
        $this->assertSame('CV Sukses Abadi', $breakdown[0]['client_name']);
        $this->assertSame('ORD-QC-999', $breakdown[0]['order_number']);
        $this->assertSame('Jaket Bomber', $breakdown[0]['article_name']);
        $this->assertSame(120, $breakdown[0]['total_passed']);

        $this->actingAs($qcUser);
        $this->get(QcBonusResource::getUrl())
            ->assertOk()
            ->assertSee('CV Sukses Abadi')
            ->assertSee('Jaket Bomber');
    }
}
