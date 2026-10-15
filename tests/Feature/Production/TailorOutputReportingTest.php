<?php

namespace Tests\Feature\Production;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\OutputEntries\Pages\ListOutputEntries;
use App\Models\Article;
use App\Models\Client;
use App\Models\Component;
use App\Models\Order;
use App\Models\OrderComponentAssignment;
use App\Models\OutputEntry;
use App\Models\User;
use App\Services\Production\ProductionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Daftar kerja penjahit (FR-009, FR-011, FR-012).
 *
 * Tiap baris adalah satu tugas: komponen yang dipegang penjahit pada sebuah
 * order. Tugas itu tertutup begitu komponennya dilaporkan — tombol "Laporkan"
 * tidak lagi ditawarkan — dan terbuka lagi ketika laporannya ditolak mandor.
 */
class TailorOutputReportingTest extends TestCase
{
    use RefreshDatabase;

    private User $tailor;

    private Order $order;

    private Component $component;

    private OrderComponentAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $client = Client::factory()->create();
        $article = Article::factory()->create(['client_id' => $client->getKey()]);

        $this->component = Component::create([
            'article_id' => $article->getKey(),
            'code' => 'KOMP-01',
            'name' => 'Kerah',
            'sequence' => 1,
        ]);

        // Order harus sudah berjalan: daftar kerja penjahit hanya memuat order
        // yang Siap Produksi atau Dalam Produksi.
        $this->order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'article_id' => $article->getKey(),
            'status' => OrderStatus::IN_PRODUCTION,
        ]);

        $this->tailor = User::factory()->create(['name' => 'Siti Penjahit']);
        $this->tailor->assignRole(UserRole::TAILOR_OPERATOR->value);

        $this->assignment = OrderComponentAssignment::factory()->create([
            'order_id' => $this->order->getKey(),
            'component_id' => $this->component->getKey(),
            'user_id' => $this->tailor->getKey(),
        ]);

        $this->actingAs($this->tailor);
    }

    public function test_the_laporkan_button_is_offered_while_the_component_is_unreported(): void
    {
        Livewire::test(ListOutputEntries::class)
            ->assertCanSeeTableRecords([$this->assignment])
            ->assertTableActionVisible('report', $this->assignment)
            ->assertSee('Belum dilaporkan');
    }

    public function test_the_laporkan_button_disappears_once_the_component_is_reported(): void
    {
        $this->report();

        Livewire::test(ListOutputEntries::class)
            ->assertTableActionHidden('report', $this->assignment)
            ->assertSee('Menunggu Verifikasi');
    }

    public function test_a_verified_component_does_not_ask_for_another_report(): void
    {
        $entry = $this->report();
        app(ProductionService::class)->verifyOutput($entry, $this->productionLead());

        Livewire::test(ListOutputEntries::class)
            ->assertTableActionHidden('report', $this->assignment)
            ->assertSee('Terverifikasi');
    }

    public function test_the_laporkan_button_returns_when_the_report_is_rejected(): void
    {
        $entry = $this->report();
        app(ProductionService::class)->rejectOutput($entry, 'Jahitan kerah tidak rapi', $this->productionLead());

        Livewire::test(ListOutputEntries::class)
            ->assertTableActionVisible('report', $this->assignment)
            ->assertSee('Ditolak')
            ->assertSee('Jahitan kerah tidak rapi');
    }

    public function test_the_newest_report_decides_whether_the_task_is_still_open(): void
    {
        // Laporan lama yang sudah terverifikasi tidak menutup tugas yang
        // laporan barunya ditolak: penjahit tetap harus mengerjakan ulang.
        $verified = $this->report(at: Carbon::parse('2026-10-12 08:05'));
        app(ProductionService::class)->verifyOutput($verified, $this->productionLead());

        $rejected = $this->report(at: Carbon::parse('2026-10-12 08:35'));
        app(ProductionService::class)->rejectOutput($rejected, 'Ukuran meleset', $this->productionLead());

        $this->assertFalse($this->assignment->isReported());

        Livewire::test(ListOutputEntries::class)
            ->assertTableActionVisible('report', $this->assignment);
    }

    /**
     * Melaporkan komponen lewat jalur tulis yang sebenarnya (FR-011).
     */
    private function report(?Carbon $at = null): OutputEntry
    {
        return app(ProductionService::class)->submitOutput(
            order: $this->order,
            line: null,
            operator: $this->tailor,
            component: $this->component,
            quantity: 12,
            intervalAt: $at ?? Carbon::parse('2026-10-12 08:05'),
        );
    }

    private function productionLead(): User
    {
        $lead = User::factory()->create();
        $lead->assignRole(UserRole::PRODUCTION_LEAD->value);

        return $lead;
    }
}
