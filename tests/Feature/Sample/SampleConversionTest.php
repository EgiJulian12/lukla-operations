<?php

namespace Tests\Feature\Sample;

use App\Enums\OrderStatus;
use App\Enums\SampleStatus;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Exceptions\InvalidSampleState;
use App\Models\Article;
use App\Models\Sample;
use App\Models\User;
use App\Services\Sample\SampleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Skenario penerimaan #4 — alur revisi sample ke order massal (FR-001..004).
 */
class SampleConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_an_approved_revision_converts_to_a_semi_cmt_mass_order(): void
    {
        $service = app(SampleService::class);
        $article = Article::factory()->create();

        $sample = $service->createSample($article, 'SMP-9001', 'Kaos Raglan');
        $v1 = $service->addRevision($sample, ['bahan' => 'Katun'], ['kain' => 1.2]);
        $v2 = $service->addRevision($sample, ['bahan' => 'Katun Combed'], ['kain' => 1.35]);

        $this->assertSame(1, $v1->version);
        $this->assertSame(2, $v2->version);

        $owner = User::factory()->create();
        $owner->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $service->submitForReview($v2);
        $service->approveRevision($v2, $owner);

        $sample->refresh();
        $this->assertSame(SampleStatus::APPROVED, $sample->status);
        $this->assertSame($v2->getKey(), $sample->approved_revision_id);

        $order = $service->convertToOrder($sample, WorkScheme::SEMI_CMT, 240);

        $this->assertSame(WorkScheme::SEMI_CMT, $order->work_scheme);
        $this->assertSame($v2->getKey(), $order->sample_revision_id);
        $this->assertSame($sample->client_id, $order->client_id);
        $this->assertSame(OrderStatus::CONFIRMED, $order->status);
        $this->assertStringContainsString('v2', $order->order_number);
    }

    public function test_a_revision_cannot_be_approved_before_it_is_submitted_for_review(): void
    {
        $service = app(SampleService::class);
        $sample = $service->createSample(Article::factory()->create(), 'SMP-9002', 'Kemeja');
        $revision = $service->addRevision($sample);

        $this->expectException(InvalidSampleState::class);

        $service->approveRevision($revision);
    }

    public function test_converting_a_sample_without_an_approved_revision_is_rejected(): void
    {
        $sample = Sample::factory()->create();

        $this->expectException(InvalidSampleState::class);

        app(SampleService::class)->convertToOrder($sample, WorkScheme::CMT, 100);
    }

    public function test_converting_twice_yields_distinct_order_numbers(): void
    {
        $service = app(SampleService::class);
        $sample = $service->createSample(Article::factory()->create(), 'SMP-9003', 'Jaket');
        $revision = $service->addRevision($sample);
        $service->submitForReview($revision);
        $service->approveRevision($revision);
        $sample->refresh();

        $first = $service->convertToOrder($sample, WorkScheme::CMT, 100);
        $second = $service->convertToOrder($sample, WorkScheme::CMT, 100);

        $this->assertNotSame($first->order_number, $second->order_number);
    }

    public function test_revision_time_study_is_recorded_against_the_revision(): void
    {
        $service = app(SampleService::class);
        $sample = $service->createSample(Article::factory()->create(), 'SMP-9004', 'Polo');
        $revision = $service->addRevision($sample);

        $study = $service->recordTimeStudy($revision, $revision->sample->article->components()->create([
            'code' => 'CMP-9004',
            'name' => 'Kerah',
            'sequence' => 1,
        ]), 95);

        $this->assertSame(95, $study->duration_seconds);
        $this->assertSame($revision->getKey(), $study->sample_revision_id);
        $this->assertSame($revision->client_id, $study->client_id);
    }
}
