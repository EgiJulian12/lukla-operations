<?php

namespace Tests\Feature\Production;

use App\Enums\OrderStatus;
use App\Enums\OutputStatus;
use App\Enums\UserRole;
use App\Exceptions\AssemblyNotReady;
use App\Filament\Resources\OutputReviews\Pages\ListOutputReviews;
use App\Filament\Resources\OutputReviews\Pages\ViewOutputReview;
use App\Filament\Resources\OutputReviews\RelationManagers\OutputEntriesRelationManager;
use App\Models\Article;
use App\Models\Assembly;
use App\Models\Client;
use App\Models\Component;
use App\Models\Order;
use App\Models\OutputEntry;
use App\Models\User;
use App\Services\Production\ProductionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifikasi output berbasis order dan gerbang perakitan (FR-012 → FR-013).
 *
 * Kepala Produksi memeriksa pekerjaan satu order, bukan baris output lepas:
 * daftarnya berbariskan order, halaman detailnya memuat laporan penjahit beserta
 * aksi verifikasi, dan perakitan hanya terbuka setelah seluruh komponen order
 * punya output terverifikasi.
 */
class OutputReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $lead;

    private User $tailor;

    private Client $client;

    private Article $article;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->client = Client::factory()->create();
        $this->article = Article::factory()->create(['client_id' => $this->client->getKey()]);

        $this->order = Order::factory()->create([
            'client_id' => $this->client->getKey(),
            'article_id' => $this->article->getKey(),
            'status' => OrderStatus::IN_PRODUCTION,
        ]);

        $this->tailor = User::factory()->create(['name' => 'Siti Penjahit']);
        $this->tailor->assignRole(UserRole::TAILOR_OPERATOR->value);

        $this->lead = User::factory()->create();
        $this->lead->assignRole(UserRole::PRODUCTION_LEAD->value);

        $this->actingAs($this->lead);
    }

    public function test_only_orders_in_production_with_output_appear_in_the_review_list(): void
    {
        $component = $this->component('KOMP-01', 1);
        $this->report($component, Carbon::parse('2026-10-12 08:05'));

        // Order berjalan yang belum dilaporkan siapa pun belum ada yang bisa diperiksa.
        $unreported = Order::factory()->create([
            'client_id' => $this->client->getKey(),
            'status' => OrderStatus::IN_PRODUCTION,
        ]);

        // Order yang belum masuk lantai produksi tidak ikut antre meski ada outputnya.
        $notStarted = Order::factory()->create([
            'client_id' => $this->client->getKey(),
            'status' => OrderStatus::READY_FOR_PRODUCTION,
        ]);

        OutputEntry::factory()->create([
            'order_id' => $notStarted->getKey(),
            'client_id' => $this->client->getKey(),
        ]);

        Livewire::test(ListOutputReviews::class)
            ->assertCanSeeTableRecords([$this->order])
            ->assertCanNotSeeTableRecords([$unreported, $notStarted]);
    }

    public function test_verify_all_verifies_every_pending_output_and_leaves_the_others_alone(): void
    {
        $first = $this->component('KOMP-01', 1);
        $second = $this->component('KOMP-02', 2);

        $pendingFirst = $this->report($first, Carbon::parse('2026-10-12 08:05'));
        $pendingSecond = $this->report($second, Carbon::parse('2026-10-12 08:35'));

        $alreadyVerified = $this->report($first, Carbon::parse('2026-10-12 09:05'));
        app(ProductionService::class)->verifyOutput($alreadyVerified, $this->lead);

        $rejected = $this->report($second, Carbon::parse('2026-10-12 09:35'));
        app(ProductionService::class)->rejectOutput($rejected, 'Jahitan kerah tidak rapi');

        $verified = app(ProductionService::class)->verifyAllPendingOutputs($this->order, $this->lead);

        $this->assertSame(2, $verified);
        $this->assertSame(OutputStatus::VERIFIED, $pendingFirst->refresh()->status);
        $this->assertSame(OutputStatus::VERIFIED, $pendingSecond->refresh()->status);
        $this->assertSame($this->lead->getKey(), $pendingFirst->verified_by);

        // Penolakan adalah keputusan mandor yang tidak ikut dibatalkan.
        $this->assertSame(OutputStatus::REJECTED, $rejected->refresh()->status);
        $this->assertSame(0, $this->order->pendingOutputVerificationCount());
    }

    public function test_assembly_is_refused_until_every_component_has_verified_output(): void
    {
        $first = $this->component('KOMP-01', 1);
        $this->component('KOMP-02', 2);

        $verified = $this->report($first, Carbon::parse('2026-10-12 08:05'));
        app(ProductionService::class)->verifyOutput($verified, $this->lead);

        $this->expectException(AssemblyNotReady::class);
        $this->expectExceptionMessage('KOMP-02');

        app(ProductionService::class)->assemble($this->order, [$verified->getKey()], 20);
    }

    public function test_an_order_is_not_ready_for_assembly_while_anything_is_still_waiting(): void
    {
        $component = $this->component('KOMP-01', 1);

        $this->assertFalse($this->order->isReadyForAssembly());

        $entry = $this->report($component, Carbon::parse('2026-10-12 08:05'));

        // Sudah dilaporkan, tetapi keputusan mandor belum ada.
        $this->assertFalse($this->order->isReadyForAssembly());

        app(ProductionService::class)->verifyOutput($entry, $this->lead);

        $this->assertTrue($this->order->isReadyForAssembly());
    }

    public function test_an_order_without_components_is_ready_once_an_output_is_verified(): void
    {
        // Artikel order ini belum dipecah menjadi komponen, sehingga tidak ada
        // komponen yang bisa ditunggu — cukup ada pekerjaan yang sudah terverifikasi.
        $entry = OutputEntry::factory()->create([
            'order_id' => $this->order->getKey(),
            'client_id' => $this->client->getKey(),
            'user_id' => $this->tailor->getKey(),
        ]);

        $this->assertFalse($this->order->isReadyForAssembly());

        app(ProductionService::class)->verifyOutput($entry, $this->lead);

        $this->assertTrue($this->order->isReadyForAssembly());
    }

    public function test_the_detail_page_offers_verify_all_only_while_something_is_pending(): void
    {
        $component = $this->component('KOMP-01', 1);
        $entry = $this->report($component, Carbon::parse('2026-10-12 08:05'));

        Livewire::test(ViewOutputReview::class, ['record' => $this->order->getRouteKey()])
            ->assertSuccessful()
            ->assertActionVisible('verifyAll')
            ->callAction('verifyAll')
            ->assertHasNoActionErrors();

        $this->assertSame(OutputStatus::VERIFIED, $entry->refresh()->status);

        // Tidak ada lagi yang menunggu keputusan, jadi tombolnya tidak ditawarkan.
        Livewire::test(ViewOutputReview::class, ['record' => $this->order->getRouteKey()])
            ->assertActionHidden('verifyAll');
    }

    public function test_the_assembly_button_unlocks_once_every_component_is_verified(): void
    {
        $first = $this->component('KOMP-01', 1);
        $second = $this->component('KOMP-02', 2);

        $firstEntry = $this->report($first, Carbon::parse('2026-10-12 08:05'));
        $secondEntry = $this->report($second, Carbon::parse('2026-10-12 08:35'));

        $routeKey = $this->order->getRouteKey();

        Livewire::test(ViewOutputReview::class, ['record' => $routeKey])
            ->assertActionVisible('assembly')
            ->assertActionDisabled('assembly');

        app(ProductionService::class)->verifyOutput($firstEntry, $this->lead);

        // Komponen kedua masih menggantung, jadi perakitan belum boleh.
        Livewire::test(ViewOutputReview::class, ['record' => $routeKey])
            ->assertActionDisabled('assembly');

        app(ProductionService::class)->verifyOutput($secondEntry, $this->lead);

        Livewire::test(ViewOutputReview::class, ['record' => $routeKey])
            ->assertActionEnabled('assembly');
    }

    public function test_the_assembly_button_records_an_assembly_from_verified_output(): void
    {
        $first = $this->component('KOMP-01', 1);
        $second = $this->component('KOMP-02', 2);

        $firstEntry = $this->report($first, Carbon::parse('2026-10-12 08:05'));
        $secondEntry = $this->report($second, Carbon::parse('2026-10-12 08:35'));

        app(ProductionService::class)->verifyOutput($firstEntry, $this->lead);
        app(ProductionService::class)->verifyOutput($secondEntry, $this->lead);

        Livewire::test(ViewOutputReview::class, ['record' => $this->order->getRouteKey()])
            ->assertActionEnabled('assembly')
            ->callAction('assembly', [
                'output_entry_ids' => [$firstEntry->getKey(), $secondEntry->getKey()],
                'quantity' => 18,
                'notes' => 'Perakitan batch pertama',
            ])
            ->assertHasNoActionErrors();

        $assembly = Assembly::query()->where('order_id', $this->order->getKey())->sole();

        $this->assertSame(18, $assembly->quantity);
        $this->assertSame('Perakitan batch pertama', $assembly->notes);
        $this->assertSame($this->lead->getKey(), $assembly->assembled_by);
        $this->assertTrue($assembly->outputEntries()->whereKey($firstEntry->getKey())->exists());
        $this->assertTrue($assembly->outputEntries()->whereKey($secondEntry->getKey())->exists());

        // Perakitan adalah catatan peristiwa: ordernya tetap di lantai produksi
        // dan boleh dirakit lagi untuk sisa unit.
        $this->assertSame(OrderStatus::IN_PRODUCTION, $this->order->refresh()->status);
        $this->assertTrue($this->order->isReadyForAssembly());
    }

    public function test_the_output_list_on_the_detail_page_verifies_one_report(): void
    {
        $component = $this->component('KOMP-01', 1);
        $entry = $this->report($component, Carbon::parse('2026-10-12 08:05'));

        Livewire::test(OutputEntriesRelationManager::class, [
            'ownerRecord' => $this->order,
            'pageClass' => ViewOutputReview::class,
        ])
            ->assertTableActionVisible('verify', $entry)
            ->callTableAction('verify', $entry)
            ->assertHasNoTableActionErrors();

        $this->assertSame(OutputStatus::VERIFIED, $entry->refresh()->status);
        $this->assertSame($this->lead->getKey(), $entry->verified_by);
    }

    /**
     * Mencatat laporan penjahit lewat jalur tulis yang sebenarnya (FR-011).
     */
    private function report(Component $component, Carbon $at, int $quantity = 12): OutputEntry
    {
        return app(ProductionService::class)->submitOutput(
            order: $this->order,
            line: null,
            operator: $this->tailor,
            component: $component,
            quantity: $quantity,
            intervalAt: $at,
        );
    }

    /**
     * Komponen artikel order yang sedang diuji.
     */
    private function component(string $code, int $sequence): Component
    {
        return Component::create([
            'article_id' => $this->article->getKey(),
            'code' => $code,
            'name' => "Komponen {$code}",
            'sequence' => $sequence,
        ]);
    }
}
