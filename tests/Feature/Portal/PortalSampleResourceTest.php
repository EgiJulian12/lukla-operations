<?php

namespace Tests\Feature\Portal;

use App\Enums\SampleStatus;
use App\Enums\UserRole;
use App\Filament\Portal\Resources\ApprovedSamples\Pages\ViewPortalApprovedSample;
use App\Filament\Portal\Resources\ApprovedSamples\PortalApprovedSampleResource;
use App\Filament\Portal\Resources\Samples\Pages\ViewPortalSample;
use App\Filament\Portal\Resources\Samples\PortalSampleResource;
use App\Filament\Portal\Resources\Samples\RelationManagers\PortalRevisionsRelationManager;
use App\Filament\Resources\Samples\Pages\ViewSample;
use App\Models\Article;
use App\Models\Client;
use App\Models\Sample;
use App\Models\User;
use App\Services\Sample\SampleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pengujian Menu Sample Portal Klien Maklon & Akses Detail Owner (FR-003, FR-025, NFR-004).
 */
class PortalSampleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_portal_sample_queries_are_scoped_to_the_logged_in_client(): void
    {
        $mine = Client::factory()->create();
        $other = Client::factory()->create();

        $articleMine = Article::factory()->create(['client_id' => $mine->getKey()]);
        $articleOther = Article::factory()->create(['client_id' => $other->getKey()]);

        app(SampleService::class)->createSample($articleMine, 'SMP-MINE', 'Sample Klien Saya');
        app(SampleService::class)->createSample($articleOther, 'SMP-OTHER', 'Sample Klien Lain');

        $this->actingAs($this->clientUser($mine));

        $this->assertSame(1, PortalSampleResource::getEloquentQuery()->count());
        $this->assertSame('SMP-MINE', PortalSampleResource::getEloquentQuery()->first()->code);
    }

    public function test_client_can_open_the_portal_sample_list(): void
    {
        $mine = Client::factory()->create();
        $article = Article::factory()->create(['client_id' => $mine->getKey()]);
        app(SampleService::class)->createSample($article, 'SMP-001', 'Sample Kaos');

        $this->actingAs($this->clientUser($mine));

        $this->get(PortalSampleResource::getUrl(panel: 'portal', tenant: $mine))
            ->assertOk();
    }

    public function test_client_can_open_its_own_sample_detail(): void
    {
        $mine = Client::factory()->create();
        $article = Article::factory()->create(['client_id' => $mine->getKey()]);
        $sample = app(SampleService::class)->createSample($article, 'SMP-001', 'Sample Kaos');
        app(SampleService::class)->addRevision($sample);

        $this->actingAs($this->clientUser($mine));

        $this->get(ViewPortalSample::getUrl(['record' => $sample], panel: 'portal', tenant: $mine))
            ->assertOk();
    }

    public function test_client_cannot_open_another_clients_sample_detail(): void
    {
        $mine = Client::factory()->create();
        $other = Client::factory()->create();

        $articleOther = Article::factory()->create(['client_id' => $other->getKey()]);
        $otherSample = app(SampleService::class)->createSample($articleOther, 'SMP-OTHER', 'Sample Milik Orang');

        $this->actingAs($this->clientUser($mine));

        $this->get(ViewPortalSample::getUrl(['record' => $otherSample], panel: 'portal', tenant: $mine))
            ->assertNotFound();
    }

    public function test_owner_can_open_admin_sample_detail_page(): void
    {
        $client = Client::factory()->create();
        $article = Article::factory()->create(['client_id' => $client->getKey()]);
        $sample = app(SampleService::class)->createSample($article, 'SMP-OWNER-TEST', 'Sample Owner Test');
        app(SampleService::class)->addRevision($sample);

        $owner = User::factory()->create(['name' => 'Bapak Owner']);
        $owner->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $this->actingAs($owner);

        $this->get(ViewSample::getUrl(['record' => $sample]))
            ->assertOk();
    }

    public function test_client_can_open_the_portal_approved_sample_list(): void
    {
        $mine = Client::factory()->create();
        $article = Article::factory()->create(['client_id' => $mine->getKey()]);
        $sample = app(SampleService::class)->createSample($article, 'SMP-APP-001', 'Sample Approved Kaos');
        $v1 = app(SampleService::class)->addRevision($sample);
        $v1->update(['status' => SampleStatus::APPROVED]);
        $sample->update(['status' => SampleStatus::APPROVED, 'approved_revision_id' => $v1->getKey()]);

        $this->actingAs($this->clientUser($mine));

        $this->get(PortalApprovedSampleResource::getUrl(panel: 'portal', tenant: $mine))
            ->assertOk();
    }

    public function test_client_can_open_portal_approved_sample_detail(): void
    {
        $mine = Client::factory()->create();
        $article = Article::factory()->create(['client_id' => $mine->getKey()]);
        $sample = app(SampleService::class)->createSample($article, 'SMP-APP-002', 'Sample Approved Kaos 2');
        $v1 = app(SampleService::class)->addRevision($sample);
        $v1->update(['status' => SampleStatus::APPROVED]);
        $sample->update(['status' => SampleStatus::APPROVED, 'approved_revision_id' => $v1->getKey()]);

        $this->actingAs($this->clientUser($mine));

        $this->get(ViewPortalApprovedSample::getUrl(['record' => $sample], panel: 'portal', tenant: $mine))
            ->assertOk();
    }

    public function test_client_portal_sidebar_registers_my_samples_and_hides_approved_samples_menu(): void
    {
        $this->assertTrue(PortalSampleResource::shouldRegisterNavigation());
        $this->assertFalse(PortalApprovedSampleResource::shouldRegisterNavigation());
    }

    public function test_client_can_approve_sample_revision_via_portal_relation_manager(): void
    {
        $client = Client::factory()->create();
        $user = $this->clientUser($client);
        $article = Article::factory()->create(['client_id' => $client->getKey()]);
        $sample = app(SampleService::class)->createSample($article, 'SMP-REV-01', 'Sample Kemeja Review');
        $v1 = app(SampleService::class)->addRevision($sample);
        app(SampleService::class)->submitForReview($v1);

        $this->actingAs($user);

        Livewire::test(PortalRevisionsRelationManager::class, [
            'ownerRecord' => $sample,
            'pageClass' => ViewPortalSample::class,
        ])
            ->assertTableActionVisible('approve', $v1)
            ->callTableAction('approve', $v1)
            ->assertHasNoTableActionErrors();

        $v1->refresh();
        $sample->refresh();

        $this->assertSame(SampleStatus::APPROVED, $v1->status);
        $this->assertSame(SampleStatus::APPROVED, $sample->status);
        $this->assertSame($user->getKey(), $v1->approved_by);
        $this->assertSame($v1->getKey(), $sample->approved_revision_id);
    }

    public function test_client_can_reject_sample_revision_via_portal_relation_manager(): void
    {
        $client = Client::factory()->create();
        $user = $this->clientUser($client);
        $article = Article::factory()->create(['client_id' => $client->getKey()]);
        $sample = app(SampleService::class)->createSample($article, 'SMP-REV-02', 'Sample Jaket Review');
        $v1 = app(SampleService::class)->addRevision($sample);
        app(SampleService::class)->submitForReview($v1);

        $this->actingAs($user);

        Livewire::test(PortalRevisionsRelationManager::class, [
            'ownerRecord' => $sample,
            'pageClass' => ViewPortalSample::class,
        ])
            ->assertTableActionVisible('reject', $v1)
            ->callTableAction('reject', $v1, data: [
                'reason' => 'Perbaiki potongan kerah dan jahitan saku.',
            ])
            ->assertHasNoTableActionErrors();

        $v1->refresh();
        $sample->refresh();

        $this->assertSame(SampleStatus::REJECTED, $v1->status);
        $this->assertSame(SampleStatus::REJECTED, $sample->status);
        $this->assertSame('Perbaiki potongan kerah dan jahitan saku.', $v1->notes);
    }

    private function clientUser(Client $client): User
    {
        $user = User::factory()->create(['client_id' => $client->getKey()]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        return $user;
    }
}
