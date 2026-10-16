<?php

namespace Tests\Feature;

use App\Enums\RateCardStatus;
use App\Enums\RateType;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\Client;
use App\Models\Component;
use App\Models\RateCard;
use App\Models\User;
use App\Services\Payroll\RateCardResolver;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateCardApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_newly_created_rate_card_defaults_to_pending_status(): void
    {
        $client = Client::factory()->create();
        $component = Component::factory()->create();

        $rateCard = RateCard::create([
            'client_id' => $client->getKey(),
            'article_id' => $component->article_id,
            'component_id' => $component->getKey(),
            'rate_type' => RateType::SEWING,
            'rate' => 1500,
            'effective_from' => now()->toDateString(),
        ]);

        $this->assertEquals(RateCardStatus::PENDING, $rateCard->status);
        $this->assertNull($rateCard->approved_by);
        $this->assertNull($rateCard->approved_at);
    }

    public function test_owner_or_finance_can_approve_pending_rate_card(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $rateCard = RateCard::factory()->pending()->create();

        $this->actingAs($owner);

        $this->assertTrue($owner->can('approve', $rateCard));

        $rateCard->approve($owner);

        $rateCard->refresh();
        $this->assertEquals(RateCardStatus::APPROVED, $rateCard->status);
        $this->assertEquals($owner->getKey(), $rateCard->approved_by);
        $this->assertNotNull($rateCard->approved_at);
    }

    public function test_production_lead_cannot_approve_rate_cards(): void
    {
        $mandor = User::factory()->create();
        $mandor->assignRole(UserRole::PRODUCTION_LEAD->value);

        $rateCard = RateCard::factory()->pending()->create();

        $this->actingAs($mandor);

        $this->assertFalse($mandor->can('approve', $rateCard));
    }

    public function test_owner_can_reject_rate_card_with_notes(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $rateCard = RateCard::factory()->pending()->create([
            'rate' => 3000,
            'notes' => 'Catatan awal',
        ]);

        $rateCard->reject('Terlalu mahal, target Rp 2000', $owner);

        $rateCard->refresh();
        $this->assertEquals(RateCardStatus::REJECTED, $rateCard->status);
        $this->assertStringContainsString('Terlalu mahal, target Rp 2000', $rateCard->notes);
    }

    public function test_in_place_update_resets_rejected_status_to_pending_without_new_record(): void
    {
        $rateCard = RateCard::factory()->rejected('Kemahalan')->create([
            'rate' => 3000,
        ]);

        $initialCount = RateCard::count();

        // Mandor melakukan in-place update menimpa harga
        $rateCard->update([
            'rate' => 2000,
        ]);

        $rateCard->refresh();

        // Memastikan tidak ada record baru (in-place update)
        $this->assertEquals($initialCount, RateCard::count());
        $this->assertEquals(2000, (float) $rateCard->rate);
        $this->assertEquals(RateCardStatus::PENDING, $rateCard->status);
        $this->assertNull($rateCard->approved_by);
        $this->assertNull($rateCard->approved_at);
    }

    public function test_rate_card_resolver_only_resolves_approved_rate_cards(): void
    {
        $client = Client::factory()->create();
        $article = Article::factory()->create(['client_id' => $client->getKey()]);
        $component = Component::factory()->create(['article_id' => $article->getKey()]);

        // Buat rate card komponen yang masih PENDING
        RateCard::factory()->pending()->create([
            'client_id' => $client->getKey(),
            'article_id' => $article->getKey(),
            'component_id' => $component->getKey(),
            'rate' => 2500,
            'effective_from' => now()->subDay()->toDateString(),
        ]);

        $resolver = app(RateCardResolver::class);

        // Resolver harus mengabaikan rate card PENDING dan mengembalikan 0
        $resolvedRate = $resolver->rateFor(
            clientId: $client->getKey(),
            articleId: $article->getKey(),
            componentId: $component->getKey(),
            type: RateType::SEWING,
            date: now(),
        );

        $this->assertEquals(0, $resolvedRate);

        // Setelah disetujui (APPROVED), resolver harus menemukannya
        $pendingCard = RateCard::query()->where('component_id', $component->getKey())->first();
        $owner = User::factory()->create();
        $owner->assignRole(UserRole::OWNER_EXECUTIVE->value);
        $pendingCard->approve($owner);

        $resolvedApprovedRate = $resolver->rateFor(
            clientId: $client->getKey(),
            articleId: $article->getKey(),
            componentId: $component->getKey(),
            type: RateType::SEWING,
            date: now(),
        );

        $this->assertEquals(2500, $resolvedApprovedRate);
    }
}
