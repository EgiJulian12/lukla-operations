<?php

namespace Tests\Feature\Sample;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Filament\Resources\Samples\Widgets\PendingSampleOrdersWidget;
use App\Models\Article;
use App\Models\Client;
use App\Models\Order;
use App\Models\Sample;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PendingSampleOrdersWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_only_orders_with_sample_requested_appear_in_widget(): void
    {
        $sampleTeam = User::factory()->create();
        $sampleTeam->assignRole(UserRole::SAMPLE_TEAM->value);
        $this->actingAs($sampleTeam);

        $client = Client::factory()->create();

        // Order A: Quote Approved tapi belum dikirim permintaan sample oleh Owner
        $orderA = Order::factory()->create([
            'client_id' => $client->getKey(),
            'order_number' => 'ORD-WAITING-REQUEST',
            'status' => OrderStatus::QUOTE_APPROVED->value,
            'quote_approved_at' => now(),
            'sample_requested_at' => null,
            'sample_id' => null,
            'work_scheme' => WorkScheme::CMT,
        ]);

        // Order B: Quote Approved dan SUDAH dikirim permintaan sample oleh Owner
        $orderB = Order::factory()->create([
            'client_id' => $client->getKey(),
            'order_number' => 'ORD-READY-FOR-SAMPLE',
            'status' => OrderStatus::QUOTE_APPROVED->value,
            'quote_approved_at' => now(),
            'sample_requested_at' => now(),
            'sample_id' => null,
            'work_scheme' => WorkScheme::FULL_PACKAGE,
        ]);

        Livewire::test(PendingSampleOrdersWidget::class)
            ->assertCanSeeTableRecords([$orderB])
            ->assertCanNotSeeTableRecords([$orderA]);
    }

    public function test_sample_team_can_start_sample_with_new_article_mode(): void
    {
        $sampleTeam = User::factory()->create();
        $sampleTeam->assignRole(UserRole::SAMPLE_TEAM->value);
        $this->actingAs($sampleTeam);

        $client = Client::factory()->create();
        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'order_number' => 'ORD-NEW-ART-001',
            'description' => 'Hoodie Zipper Fleece Cotton Combed',
            'status' => OrderStatus::QUOTE_APPROVED->value,
            'quote_approved_at' => now(),
            'sample_requested_at' => now(),
            'sample_id' => null,
            'work_scheme' => WorkScheme::FULL_PACKAGE,
        ]);

        Livewire::test(PendingSampleOrdersWidget::class)
            ->mountAction(TestAction::make('createSample')->table($order))
            ->fillForm([
                'article_mode' => 'new',
                'name' => 'Hoodie Zipper Streetwear',
                'code' => 'ART-HD-001',
                'description' => 'Bahan cotton fleece 330 gsm',
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $order->refresh();
        $this->assertNotNull($order->article_id);
        $this->assertNotNull($order->sample_id);

        $article = Article::find($order->article_id);
        $this->assertSame('Hoodie Zipper Streetwear', $article->name);
        $this->assertSame('ART-HD-001', $article->code);

        $sample = Sample::find($order->sample_id);
        $this->assertSame($article->getKey(), $sample->article_id);
        $this->assertSame('v1', $sample->currentRevision->version_label);

        // Setelah sample dibuat, pesanan tidak lagi muncul di antrean widget
        Livewire::test(PendingSampleOrdersWidget::class)
            ->assertCanNotSeeTableRecords([$order]);
    }

    public function test_sample_team_can_start_sample_with_existing_article_mode(): void
    {
        $sampleTeam = User::factory()->create();
        $sampleTeam->assignRole(UserRole::SAMPLE_TEAM->value);
        $this->actingAs($sampleTeam);

        $client = Client::factory()->create();
        $existingArticle = Article::factory()->create([
            'client_id' => $client->getKey(),
            'name' => 'T-Shirt Oversize Signature',
            'code' => 'ART-TS-SIG',
        ]);

        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'order_number' => 'ORD-EXISTING-ART-002',
            'description' => 'Repeat order T-shirt warna sage green',
            'status' => OrderStatus::QUOTE_APPROVED->value,
            'quote_approved_at' => now(),
            'sample_requested_at' => now(),
            'sample_id' => null,
            'work_scheme' => WorkScheme::CMT,
        ]);

        Livewire::test(PendingSampleOrdersWidget::class)
            ->mountAction(TestAction::make('createSample')->table($order))
            ->fillForm([
                'article_mode' => 'existing',
                'article_id' => $existingArticle->getKey(),
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $order->refresh();
        $this->assertSame($existingArticle->getKey(), $order->article_id);
        $this->assertNotNull($order->sample_id);

        $sample = Sample::find($order->sample_id);
        $this->assertSame($existingArticle->getKey(), $sample->article_id);

        // Setelah sample dibuat, pesanan tidak lagi muncul di antrean widget
        Livewire::test(PendingSampleOrdersWidget::class)
            ->assertCanNotSeeTableRecords([$order]);
    }

    public function test_sample_team_can_start_sample_with_existing_article_when_previous_sample_already_exists(): void
    {
        $sampleTeam = User::factory()->create();
        $sampleTeam->assignRole(UserRole::SAMPLE_TEAM->value);
        $this->actingAs($sampleTeam);

        $client = Client::factory()->create();

        // Order 1 dan Sample 1 perdana sudah ada
        $order1 = Order::factory()->create([
            'client_id' => $client->getKey(),
            'order_number' => 'REQ-202610-0001',
            'status' => OrderStatus::QUOTE_APPROVED->value,
        ]);

        $article = Article::factory()->create([
            'client_id' => $client->getKey(),
            'name' => 'Baju Kaos Test',
            'code' => 'REQ-202610-0001',
        ]);

        $sample1 = Sample::factory()->create([
            'client_id' => $client->getKey(),
            'article_id' => $article->getKey(),
            'code' => 'REQ-202610-0001',
            'title' => 'Baju Kaos Test',
        ]);
        $order1->update(['article_id' => $article->getKey(), 'sample_id' => $sample1->getKey()]);

        // Order 2 datang untuk klien yang sama dan memilih artikel yang sudah ada
        $order2 = Order::factory()->create([
            'client_id' => $client->getKey(),
            'order_number' => 'REQ-202610-0002',
            'description' => 'Order repeat baju kaos test',
            'status' => OrderStatus::QUOTE_APPROVED->value,
            'quote_approved_at' => now(),
            'sample_requested_at' => now(),
            'sample_id' => null,
            'work_scheme' => WorkScheme::FULL_PACKAGE,
        ]);

        Livewire::test(PendingSampleOrdersWidget::class)
            ->mountAction(TestAction::make('createSample')->table($order2))
            ->fillForm([
                'article_mode' => 'existing',
                'article_id' => $article->getKey(),
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $order2->refresh();
        $this->assertSame($article->getKey(), $order2->article_id);
        $this->assertNotNull($order2->sample_id);

        $sample2 = Sample::find($order2->sample_id);
        $this->assertSame($article->getKey(), $sample2->article_id);
        // Kode sample kedua harus unik dan mengikuti nomor order baru, tidak menduplikasi REQ-202610-0001
        $this->assertSame('REQ-202610-0002', $sample2->code);
        $this->assertNotEquals($sample1->getKey(), $sample2->getKey());
    }
}
