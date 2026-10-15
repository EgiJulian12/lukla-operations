<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\RateCardStatus;
use App\Enums\RateType;
use App\Enums\SampleStatus;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Filament\Resources\ApprovedSamples\ApprovedSampleResource;
use App\Filament\Resources\ApprovedSamples\Pages\ListApprovedSamples;
use App\Filament\Resources\ApprovedSamples\Pages\ViewApprovedSample;
use App\Models\Article;
use App\Models\Client;
use App\Models\Component;
use App\Models\Order;
use App\Models\RateCard;
use App\Models\Sample;
use App\Models\SampleRevision;
use App\Models\TimeStudy;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApprovedSampleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $productionLead;

    protected User $sampleTeam;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->productionLead = User::factory()->create();
        $this->productionLead->assignRole(UserRole::PRODUCTION_LEAD->value);

        $this->sampleTeam = User::factory()->create();
        $this->sampleTeam->assignRole(UserRole::SAMPLE_TEAM->value);

        $this->client = Client::factory()->create();
    }

    public function test_only_approved_samples_appear_in_resource_query(): void
    {
        $approvedSample = Sample::factory()->create([
            'client_id' => $this->client->id,
            'status' => SampleStatus::APPROVED,
        ]);

        $draftSample = Sample::factory()->create([
            'client_id' => $this->client->id,
            'status' => SampleStatus::DRAFT,
        ]);

        $records = ApprovedSampleResource::getEloquentQuery()->get();

        $this->assertTrue($records->contains($approvedSample));
        $this->assertFalse($records->contains($draftSample));
    }

    public function test_production_lead_can_render_list_page_and_tabs_filtered_by_order_scheme(): void
    {
        $cmtSample = Sample::factory()->create([
            'client_id' => $this->client->id,
            'status' => SampleStatus::APPROVED,
        ]);
        Order::factory()->create([
            'sample_id' => $cmtSample->id,
            'client_id' => $this->client->id,
            'work_scheme' => WorkScheme::CMT,
        ]);

        $semiSample = Sample::factory()->create([
            'client_id' => $this->client->id,
            'status' => SampleStatus::APPROVED,
        ]);
        Order::factory()->create([
            'sample_id' => $semiSample->id,
            'client_id' => $this->client->id,
            'work_scheme' => WorkScheme::SEMI_CMT,
        ]);

        $fullSample = Sample::factory()->create([
            'client_id' => $this->client->id,
            'status' => SampleStatus::APPROVED,
        ]);
        Order::factory()->create([
            'sample_id' => $fullSample->id,
            'client_id' => $this->client->id,
            'work_scheme' => WorkScheme::FULL_PACKAGE,
        ]);

        $this->actingAs($this->productionLead);

        $response = $this->get(ApprovedSampleResource::getUrl('index'));
        $response->assertSuccessful();

        Livewire::test(ListApprovedSamples::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$cmtSample, $semiSample, $fullSample])
            ->set('activeTab', 'cmt')
            ->assertCanSeeTableRecords([$cmtSample])
            ->assertCanNotSeeTableRecords([$semiSample, $fullSample])
            ->set('activeTab', 'semi_cmt')
            ->assertCanSeeTableRecords([$semiSample])
            ->assertCanNotSeeTableRecords([$cmtSample, $fullSample])
            ->set('activeTab', 'full_package')
            ->assertCanSeeTableRecords([$fullSample])
            ->assertCanNotSeeTableRecords([$cmtSample, $semiSample]);
    }

    public function test_production_lead_can_view_full_detail_page_with_components_and_time_study(): void
    {
        $article = Article::factory()->create(['client_id' => $this->client->id]);

        $comp1 = Component::create([
            'article_id' => $article->id,
            'code' => 'KERAH-01',
            'name' => 'Kerah Baju',
            'sequence' => 1,
        ]);

        $comp2 = Component::create([
            'article_id' => $article->id,
            'code' => 'LENGAN-01',
            'name' => 'Pasang Lengan',
            'sequence' => 2,
        ]);

        $sample = Sample::factory()->create([
            'client_id' => $this->client->id,
            'article_id' => $article->id,
            'title' => 'Sample Kemeja Katun',
            'status' => SampleStatus::APPROVED,
        ]);

        $revision = SampleRevision::create([
            'client_id' => $this->client->id,
            'sample_id' => $sample->id,
            'version' => 1,
            'specs' => ['Ukuran' => 'L', 'Gramasi' => '180 GSM'],
            'material_usage' => ['Kain Katun' => '1.5 meter'],
            'status' => SampleStatus::APPROVED,
            'approved_by' => $this->sampleTeam->id,
            'approved_at' => now(),
        ]);

        $sample->update(['approved_revision_id' => $revision->id]);

        TimeStudy::create([
            'client_id' => $this->client->id,
            'sample_revision_id' => $revision->id,
            'component_id' => $comp1->id,
            'duration_seconds' => 120,
            'recorded_at' => now(),
        ]);

        RateCard::create([
            'client_id' => $this->client->id,
            'article_id' => $article->id,
            'component_id' => $comp1->id,
            'rate_type' => RateType::SEWING,
            'rate' => 2500,
            'effective_from' => now()->subDay()->toDateString(),
            'status' => RateCardStatus::APPROVED,
            'approved_by' => $this->productionLead->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($this->productionLead);

        $response = $this->get(ApprovedSampleResource::getUrl('view', ['record' => $sample]));
        $response->assertSuccessful();

        Livewire::test(ViewApprovedSample::class, ['record' => $sample->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Sample Kemeja Katun')
            ->assertSee('Kerah Baju')
            ->assertSee('Pasang Lengan')
            ->assertSee('120 detik')
            ->assertSee('2.500');
    }

    public function test_sample_team_can_convert_approved_sample_to_order_from_detail_page(): void
    {
        $article = Article::factory()->create(['client_id' => $this->client->id]);

        $sample = Sample::factory()->create([
            'client_id' => $this->client->id,
            'article_id' => $article->id,
            'status' => SampleStatus::APPROVED,
        ]);

        $revision = SampleRevision::create([
            'client_id' => $this->client->id,
            'sample_id' => $sample->id,
            'version' => 1,
            'status' => SampleStatus::APPROVED,
            'approved_by' => $this->sampleTeam->id,
            'approved_at' => now(),
        ]);

        $sample->update(['approved_revision_id' => $revision->id]);

        // Skema, jumlah, dan tenggat mengikuti order terakhir sample — modal hanya
        // menampilkannya sebagai informasi, sehingga catatan adalah satu-satunya isian.
        Order::factory()->create([
            'sample_id' => $sample->id,
            'client_id' => $this->client->id,
            'article_id' => $article->id,
            'work_scheme' => WorkScheme::SEMI_CMT,
            'quantity' => 200,
            'due_date' => now()->addWeeks(2)->toDateString(),
        ]);

        $this->actingAs($this->sampleTeam);

        Livewire::test(ViewApprovedSample::class, ['record' => $sample->getRouteKey()])
            ->assertActionVisible('convert')
            ->callAction('convert', [
                'notes' => 'Pesanan batch pertama',
            ])
            ->assertHasNoActionErrors();

        $order = Order::query()->where('sample_id', $sample->id)->latest('id')->firstOrFail();

        $this->assertSame($article->id, $order->article_id);
        $this->assertSame(200, $order->quantity);
        $this->assertSame(WorkScheme::SEMI_CMT, $order->work_scheme);
        $this->assertSame(OrderStatus::READY_FOR_PRODUCTION, $order->status);
        $this->assertSame('Pesanan batch pertama', $order->notes);
    }

    public function test_production_lead_can_update_component_rate_card_from_detail_page(): void
    {
        $article = Article::factory()->create(['client_id' => $this->client->id]);

        $comp = Component::create([
            'article_id' => $article->id,
            'code' => 'KERAH-01',
            'name' => 'Kerah Baju',
            'sequence' => 1,
        ]);

        $sample = Sample::factory()->create([
            'client_id' => $this->client->id,
            'article_id' => $article->id,
            'status' => SampleStatus::APPROVED,
        ]);

        $revision = SampleRevision::create([
            'client_id' => $this->client->id,
            'sample_id' => $sample->id,
            'version' => 1,
            'status' => SampleStatus::APPROVED,
            'approved_by' => $this->sampleTeam->id,
            'approved_at' => now(),
        ]);

        $sample->update(['approved_revision_id' => $revision->id]);

        $rateCard = RateCard::create([
            'client_id' => $this->client->id,
            'article_id' => $article->id,
            'component_id' => $comp->id,
            'rate_type' => RateType::SEWING,
            'rate' => 2000,
            'effective_from' => now()->subDays(5)->toDateString(),
            'status' => RateCardStatus::APPROVED,
            'approved_by' => $this->productionLead->id,
            'approved_at' => now()->subDays(2),
        ]);

        $this->actingAs($this->productionLead);

        Livewire::test(ViewApprovedSample::class, ['record' => $sample->getRouteKey()])
            ->assertActionVisible('editRate');

        Livewire::test(ViewApprovedSample::class, ['record' => $sample->getRouteKey()])
            ->mountAction('editRate', ['componentId' => $comp->id])
            ->setActionData([
                'rate' => 2750,
                'notes' => 'Penyesuaian tingkat kesulitan kain oxford',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $rateCard->refresh();

        $this->assertEquals(2750, (float) $rateCard->rate);
        $this->assertEquals(RateCardStatus::PENDING, $rateCard->status);
        $this->assertNull($rateCard->approved_by);
        $this->assertNull($rateCard->approved_at);
        $this->assertEquals('Penyesuaian tingkat kesulitan kain oxford', $rateCard->notes);

        // Kepala Produksi tidak boleh approve atau reject
        Livewire::test(ViewApprovedSample::class, ['record' => $sample->getRouteKey()])
            ->assertActionHidden('approveRate')
            ->assertActionHidden('rejectRate');
    }

    public function test_finance_or_owner_can_approve_pending_rate_from_detail_page_and_cannot_edit_rate(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $article = Article::factory()->create(['client_id' => $this->client->id]);

        $comp = Component::create([
            'article_id' => $article->id,
            'code' => 'KERAH-01',
            'name' => 'Kerah Baju',
            'sequence' => 1,
        ]);

        $sample = Sample::factory()->create([
            'client_id' => $this->client->id,
            'article_id' => $article->id,
            'status' => SampleStatus::APPROVED,
        ]);

        $revision = SampleRevision::create([
            'client_id' => $this->client->id,
            'sample_id' => $sample->id,
            'version' => 1,
            'status' => SampleStatus::APPROVED,
            'approved_by' => $this->sampleTeam->id,
            'approved_at' => now(),
        ]);

        $sample->update(['approved_revision_id' => $revision->id]);

        $rateCard = RateCard::create([
            'client_id' => $this->client->id,
            'article_id' => $article->id,
            'component_id' => $comp->id,
            'rate_type' => RateType::SEWING,
            'rate' => 2500,
            'effective_from' => now()->toDateString(),
            'status' => RateCardStatus::PENDING,
        ]);

        $this->actingAs($owner);

        Livewire::test(ViewApprovedSample::class, ['record' => $sample->getRouteKey()])
            ->assertActionHidden('editRate')
            ->assertActionVisible('approveRate');

        Livewire::test(ViewApprovedSample::class, ['record' => $sample->getRouteKey()])
            ->mountAction('approveRate', ['componentId' => $comp->id])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $rateCard->refresh();

        $this->assertEquals(RateCardStatus::APPROVED, $rateCard->status);
        $this->assertEquals($owner->id, $rateCard->approved_by);
        $this->assertNotNull($rateCard->approved_at);
    }

    public function test_finance_or_owner_can_reject_pending_rate_from_detail_page(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole(UserRole::FINANCE_PAYROLL->value);

        $article = Article::factory()->create(['client_id' => $this->client->id]);

        $comp = Component::create([
            'article_id' => $article->id,
            'code' => 'KERAH-01',
            'name' => 'Kerah Baju',
            'sequence' => 1,
        ]);

        $sample = Sample::factory()->create([
            'client_id' => $this->client->id,
            'article_id' => $article->id,
            'status' => SampleStatus::APPROVED,
        ]);

        $revision = SampleRevision::create([
            'client_id' => $this->client->id,
            'sample_id' => $sample->id,
            'version' => 1,
            'status' => SampleStatus::APPROVED,
            'approved_by' => $this->sampleTeam->id,
            'approved_at' => now(),
        ]);

        $sample->update(['approved_revision_id' => $revision->id]);

        $rateCard = RateCard::create([
            'client_id' => $this->client->id,
            'article_id' => $article->id,
            'component_id' => $comp->id,
            'rate_type' => RateType::SEWING,
            'rate' => 4500,
            'effective_from' => now()->toDateString(),
            'status' => RateCardStatus::PENDING,
        ]);

        $this->actingAs($finance);

        Livewire::test(ViewApprovedSample::class, ['record' => $sample->getRouteKey()])
            ->assertActionHidden('editRate')
            ->assertActionVisible('rejectRate');

        Livewire::test(ViewApprovedSample::class, ['record' => $sample->getRouteKey()])
            ->mountAction('rejectRate', ['componentId' => $comp->id])
            ->setActionData(['reason' => 'Plafon tarif jahit kerah maksimal Rp 3.000'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $rateCard->refresh();

        $this->assertEquals(RateCardStatus::REJECTED, $rateCard->status);
        $this->assertStringContainsString('Plafon tarif jahit kerah maksimal Rp 3.000', (string) $rateCard->notes);
    }

    public function test_sample_team_cannot_see_edit_rate_action(): void
    {
        $article = Article::factory()->create(['client_id' => $this->client->id]);

        $sample = Sample::factory()->create([
            'client_id' => $this->client->id,
            'article_id' => $article->id,
            'status' => SampleStatus::APPROVED,
        ]);

        $revision = SampleRevision::create([
            'client_id' => $this->client->id,
            'sample_id' => $sample->id,
            'version' => 1,
            'status' => SampleStatus::APPROVED,
            'approved_by' => $this->sampleTeam->id,
            'approved_at' => now(),
        ]);

        $sample->update(['approved_revision_id' => $revision->id]);

        $this->actingAs($this->sampleTeam);

        Livewire::test(ViewApprovedSample::class, ['record' => $sample->getRouteKey()])
            ->assertActionHidden('editRate')
            ->assertActionHidden('approveRate')
            ->assertActionHidden('rejectRate');
    }
}
