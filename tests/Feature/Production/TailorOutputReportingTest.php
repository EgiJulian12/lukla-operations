<?php

namespace Tests\Feature\Production;

use App\Enums\OrderStatus;
use App\Enums\QcResult;
use App\Enums\UserRole;
use App\Filament\Resources\OutputEntries\Pages\ListOutputEntries;
use App\Models\Article;
use App\Models\Client;
use App\Models\Component;
use App\Models\Order;
use App\Models\OrderComponentAssignment;
use App\Models\OutputEntry;
use App\Models\QcRecord;
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
 * order. Tugas itu tidak tertutup oleh laporannya sendiri — penjahit boleh
 * melaporkan interval berikutnya selama unit yang lolos QC (FR-014) belum
 * mencukupi jumlah yang dipesan. Tombol "Kerjakan" baru hilang saat target QC
 * order-nya tercapai, sepakat dengan OrderComponentAssignment::hasReachedQcTarget().
 */
class TailorOutputReportingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Jumlah yang dipesan order uji. Angkanya dipilih bulat supaya uji batas QC
     * di bawah bisa menabrak tepat di bawah dan tepat di atas target.
     */
    private const ORDER_QUANTITY = 10;

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
        // yang Siap Produksi, Dalam Produksi, atau Dalam QC.
        $this->order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'article_id' => $article->getKey(),
            'status' => OrderStatus::IN_PRODUCTION,
            'quantity' => self::ORDER_QUANTITY,
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

    public function test_the_kerjakan_button_is_offered_until_the_order_passes_qc(): void
    {
        Livewire::test(ListOutputEntries::class)
            ->assertCanSeeTableRecords([$this->assignment])
            ->assertTableActionVisible('report', $this->assignment)
            ->assertSee('Kerjakan')
            ->assertSee('Belum dilaporkan');
    }

    /**
     * Satu laporan interval tidak menutup tugasnya: penjahit masih memegang
     * komponen itu sampai order-nya menyelesaikan QC.
     */
    public function test_the_kerjakan_button_survives_a_report_that_is_awaiting_verification(): void
    {
        $this->report();

        Livewire::test(ListOutputEntries::class)
            ->assertTableActionVisible('report', $this->assignment)
            ->assertSee('Menunggu Verifikasi');
    }

    /**
     * Verifikasi mandor saja belum menutup tugas — yang menutupnya adalah unit
     * yang lolos QC sebanyak jumlah yang dipesan.
     */
    public function test_a_verified_report_alone_does_not_close_the_task(): void
    {
        $entry = $this->report();
        app(ProductionService::class)->verifyOutput($entry, $this->productionLead());

        Livewire::test(ListOutputEntries::class)
            ->assertTableActionVisible('report', $this->assignment)
            ->assertSee('Terverifikasi');
    }

    public function test_the_kerjakan_button_closes_once_the_order_passes_the_whole_quantity(): void
    {
        $this->passQc(self::ORDER_QUANTITY);

        $this->assertTrue($this->assignment->hasReachedQcTarget());

        Livewire::test(ListOutputEntries::class)
            ->assertTableActionHidden('report', $this->assignment);
    }

    public function test_a_partial_qc_pass_leaves_the_task_open(): void
    {
        $this->passQc(self::ORDER_QUANTITY - 1);

        $this->assertFalse($this->assignment->hasReachedQcTarget());

        Livewire::test(ListOutputEntries::class)
            ->assertTableActionVisible('report', $this->assignment);
    }

    /**
     * Order yang sudah masuk QC tetap muncul di daftar kerja: justru di sana
     * jumlah unit yang lolos QC bertambah, dan itulah patokan tugas ditutup.
     */
    public function test_the_worklist_keeps_showing_components_of_an_order_in_qc(): void
    {
        $this->order->transitionTo(OrderStatus::IN_QC);

        Livewire::test(ListOutputEntries::class)
            ->assertCanSeeTableRecords([$this->assignment])
            ->assertTableActionVisible('report', $this->assignment);
    }

    /**
     * Laporan yang ditolak tidak menutup apa pun — tugasnya memang masih
     * terbuka — jadi yang tampak di baris tabel adalah alasan penolakannya.
     */
    public function test_a_rejected_report_shows_its_reason_on_the_open_task(): void
    {
        $entry = $this->report();
        app(ProductionService::class)->rejectOutput($entry, 'Jahitan kerah tidak rapi', $this->productionLead());

        Livewire::test(ListOutputEntries::class)
            ->assertTableActionVisible('report', $this->assignment)
            ->assertSee('Ditolak')
            ->assertSee('Jahitan kerah tidak rapi');
    }

    /**
     * Stopwatch penjahit mengukur pengerjaan yang sebenarnya, jadi awal dan
     * akhirnya dicatat apa adanya — bukan dibulatkan ke interval 30 menit.
     */
    public function test_the_kerja_action_stores_the_stopwatch_times_as_measured(): void
    {
        Livewire::test(ListOutputEntries::class)
            ->callTableAction('report', $this->assignment, [
                'quantity' => 8,
                'interval_start' => '2026-10-15T09:07:33',
                'interval_end' => '2026-10-15T09:22:11',
            ])
            ->assertHasNoTableActionErrors();

        $entry = OutputEntry::query()
            ->where('component_id', $this->component->getKey())
            ->sole();

        $this->assertSame('09:07:33', $entry->interval_start->format('H:i:s'));
        $this->assertSame('09:22:11', $entry->interval_end->format('H:i:s'));
        $this->assertSame(8, $entry->quantity);
    }

    /**
     * Laporan yang dikirim tanpa menekan "Stop" tetap tercatat: waktu kirim
     * dipakai sebagai akhir pengerjaan.
     */
    public function test_a_report_sent_without_stopping_the_stopwatch_ends_at_the_submission_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00'));

        Livewire::test(ListOutputEntries::class)
            ->callTableAction('report', $this->assignment, [
                'quantity' => 5,
                'interval_start' => '2026-10-15T09:30:00',
                'interval_end' => null,
            ])
            ->assertHasNoTableActionErrors();

        $entry = OutputEntry::query()
            ->where('component_id', $this->component->getKey())
            ->sole();

        $this->assertSame('09:30:00', $entry->interval_start->format('H:i:s'));
        $this->assertSame('10:00:00', $entry->interval_end->format('H:i:s'));
    }

    /**
     * Laporan hanya dapat dikirim setelah pengerjaan dihentikan: tombol kirim
     * modal mengikuti penanda work_timer_stopped, yang hanya terisi oleh
     * tombol "Stop".
     */
    public function test_the_submit_button_waits_for_the_stopwatch_to_stop(): void
    {
        $html = $this->modalHtml();

        $this->assertStringContainsString(
            'data-work-timer-stopped-path="mountedActions.0.data.work_timer_stopped"',
            $html,
        );
        $this->assertStringContainsString(
            'x-effect="$el.disabled = ! $wire.get($el.dataset.workTimerStoppedPath)"',
            $html,
        );
    }

    /**
     * Jumlah Selesai dan Catatan menyusul pengerjaan: keduanya baru tampil
     * saat stopwatch dihentikan, memakai penanda yang sama dengan tombol kirim.
     */
    public function test_the_quantity_and_notes_appear_only_after_the_stopwatch_is_stopped(): void
    {
        $this->assertSame(
            2,
            substr_count($this->modalHtml(), 'x-bind:disabled="! ($get(\'work_timer_stopped\'))"'),
            'Jumlah Selesai dan Catatan seharusnya sama-sama menunggu stopwatch dihentikan.',
        );
    }

    /**
     * Seluruh JavaScript stopwatch harus utuh di dalam atribut x-data-nya.
     * Atribut itu dibatasi tanda kutip ganda, jadi satu tanda kutip ganda di
     * dalamnya — termasuk di dalam komentar JavaScript — memotong atribut lebih
     * awal dan menumpahkan sisa skripnya sebagai teks di tombol Mulai.
     */
    public function test_the_stopwatch_javascript_stays_inside_its_attribute(): void
    {
        preg_match('/x-data="([^"]*writeStopped[^"]*)"/', $this->modalHtml(), $matches);

        $stopwatchScript = $matches[1] ?? '';

        $this->assertStringContainsString('this.writeStopped(true)', $stopwatchScript);
        $this->assertStringContainsString('destroy() {', $stopwatchScript);
    }

    /**
     * Penjahit mengikuti pencacah stopwatch, bukan jam: Waktu Selesai tidak
     * ditampilkan, dan Jumlah Selesai berdiri tepat di atas Catatan.
     */
    public function test_the_modal_hides_the_end_time_and_keeps_the_quantity_above_the_notes(): void
    {
        $html = $this->modalHtml();

        $this->assertStringContainsString('Waktu Mulai', $html);
        $this->assertStringNotContainsString('Waktu Selesai', $html);

        $labels = $this->fieldLabelsInOrder($html);

        $quantityAt = array_search('Jumlah Selesai (pcs)', $labels, true);
        $notesAt = array_search('Catatan', $labels, true);

        $this->assertNotFalse($quantityAt, 'Label Jumlah Selesai seharusnya ada di modal.');
        $this->assertNotFalse($notesAt, 'Label Catatan seharusnya ada di modal.');
        $this->assertLessThan($notesAt, $quantityAt, 'Jumlah Selesai seharusnya berdiri di atas Catatan.');
    }

    /**
     * Laporan yang lolos tanpa stopwatch — mis. peramban tanpa JavaScript,
     * sehingga tombol kirimnya tidak terkunci — tetap tercatat lewat jalur
     * lama: dibulatkan ke interval 30 menit, bukan berakhir sebelum dimulai.
     */
    public function test_a_report_without_a_started_stopwatch_falls_back_to_the_rounded_interval(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 08:05:00'));

        Livewire::test(ListOutputEntries::class)
            ->callTableAction('report', $this->assignment, [
                'quantity' => 6,
                'interval_start' => null,
                'interval_end' => null,
            ])
            ->assertHasNoTableActionErrors();

        $entry = OutputEntry::query()
            ->where('component_id', $this->component->getKey())
            ->sole();

        $this->assertSame('08:00:00', $entry->interval_start->format('H:i:s'));
        $this->assertSame('08:30:00', $entry->interval_end->format('H:i:s'));
    }

    /**
     * HTML modal "Kerjakan" yang sedang terbuka.
     *
     * Isinya tidak ada di dalam `html()` komponen: Filament mengirim modal
     * sebagai partial Livewire tersendiri (lihat
     * PartialsComponentHook::dehydrate()), sehingga yang diperiksa adalah
     * partial itu.
     */
    private function modalHtml(): string
    {
        return Livewire::test(ListOutputEntries::class)
            ->mountTableAction('report', $this->assignment)
            ->getMountedActionModalHtml();
    }

    /**
     * Label field di dalam HTML modal, berurutan sesuai tampilannya.
     *
     * Dibaca dari markup label Filament, bukan dengan mencari kata di seluruh
     * halaman: "Catatan" juga muncul di komentar JavaScript milik stopwatch,
     * jadi pencarian teks bebas menemukan kalimat, bukan field — dan urutan
     * field pun jadi tak terukur.
     *
     * @return array<int, string>
     */
    private function fieldLabelsInOrder(string $html): array
    {
        preg_match_all('/fi-fo-field-label-content">\s*([^<]+)/', $html, $matches);

        return array_map('trim', $matches[1] ?? []);
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

    /**
     * Mencatat sejumlah unit order ini yang lolos pemeriksaan QC (FR-014).
     *
     * Ditulis langsung sebagai catatan QC, bukan lewat perakitan: yang diuji di
     * sini adalah pembacaan target oleh daftar kerja penjahit, bukan alur QC.
     */
    private function passQc(int $quantity): void
    {
        QcRecord::factory()->create([
            'order_id' => $this->order->getKey(),
            'result' => QcResult::PASS,
            'quantity' => $quantity,
        ]);
    }

    private function productionLead(): User
    {
        $lead = User::factory()->create();
        $lead->assignRole(UserRole::PRODUCTION_LEAD->value);

        return $lead;
    }
}
