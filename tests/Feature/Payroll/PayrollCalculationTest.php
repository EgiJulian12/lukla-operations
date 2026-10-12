<?php

namespace Tests\Feature\Payroll;

use App\Enums\RateType;
use App\Models\Article;
use App\Models\Client;
use App\Models\Component;
use App\Models\RateCard;
use App\Services\Payroll\RateCardResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pencarian tarif yang berlaku (FR-010).
 *
 * Aturannya satu: tarif yang lebih spesifik menang, dan tarif yang belum
 * berlaku pada tanggal yang diminta tidak ikut dihitung.
 */
class PayrollCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_component_rate_beats_the_article_rate_and_the_client_base_rate(): void
    {
        $client = Client::factory()->create();
        $article = Article::factory()->create(['client_id' => $client->getKey()]);
        $component = Component::factory()->create(['article_id' => $article->getKey()]);

        RateCard::factory()->create([
            'client_id' => $client->getKey(),
            'article_id' => null,
            'component_id' => null,
            'rate' => 500,
            'effective_from' => now()->subMonths(3)->toDateString(),
        ]);

        RateCard::factory()->create([
            'client_id' => $client->getKey(),
            'article_id' => $article->getKey(),
            'component_id' => null,
            'rate' => 900,
            'effective_from' => now()->subMonths(2)->toDateString(),
        ]);

        RateCard::factory()->create([
            'client_id' => $client->getKey(),
            'article_id' => $article->getKey(),
            'component_id' => $component->getKey(),
            'rate' => 1500,
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        $rate = app(RateCardResolver::class)->rateFor(
            $client->getKey(),
            $article->getKey(),
            $component->getKey(),
            RateType::SEWING,
            now(),
        );

        $this->assertEquals(1500.0, $rate);
    }

    public function test_a_rate_card_that_is_not_yet_effective_is_ignored(): void
    {
        $client = Client::factory()->create();

        RateCard::factory()->create([
            'client_id' => $client->getKey(),
            'article_id' => null,
            'component_id' => null,
            'rate' => 750,
            'effective_from' => now()->addMonth()->toDateString(),
        ]);

        $rate = app(RateCardResolver::class)->rateFor(
            $client->getKey(),
            null,
            null,
            RateType::SEWING,
            now(),
        );

        $this->assertEquals(0.0, $rate);
    }

    public function test_a_closed_rate_card_still_governs_the_dates_it_covered(): void
    {
        $client = Client::factory()->create();

        RateCard::factory()->create([
            'client_id' => $client->getKey(),
            'article_id' => null,
            'component_id' => null,
            'rate' => 400,
            'effective_from' => now()->subMonths(4)->toDateString(),
            'effective_to' => now()->subMonths(2)->toDateString(),
        ]);

        $rate = app(RateCardResolver::class)->rateFor(
            $client->getKey(),
            null,
            null,
            RateType::SEWING,
            now()->subMonths(3),
        );

        $this->assertEquals(400.0, $rate);
    }
}
