<?php

namespace App\Services\Production;

use App\Enums\OrderStatus;
use App\Enums\OutputStatus;
use App\Enums\UserRole;
use App\Exceptions\AssemblyNotReady;
use App\Exceptions\DuplicateOutputInterval;
use App\Exceptions\IncompleteComponentAssignment;
use App\Exceptions\InvalidOrderState;
use App\Exceptions\InvalidOutputState;
use App\Exceptions\UnassignedOperatorException;
use App\Models\Assembly;
use App\Models\Component;
use App\Models\LineAssignment;
use App\Models\Order;
use App\Models\OrderComponentAssignment;
use App\Models\OutputEntry;
use App\Models\ProductionLine;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya jalur tulis untuk memulai produksi, rencana penjahit per
 * komponen, alokasi line, output 30 menit, verifikasi mandor, dan perakitan
 * (FR-009 sampai FR-013).
 *
 * Output yang sudah diverifikasi terkunci: verifikasi adalah gerbang menuju
 * payroll, dan setelah lewat gerbang itu penjahit tidak dapat mengubah catatan
 * (matriks RBAC, separasi tugas FR-012).
 *
 * Verifikasi boleh dilakukan baris per baris maupun sekaligus untuk satu order;
 * keduanya melewati aturan status yang sama, sehingga tidak ada jalur yang lebih
 * longgar dari jalur lainnya.
 */
class ProductionService
{
    /**
     * Memulai pengerjaan order massal dan menetapkan rencana penjahitnya
     * (FR-004 → FR-009).
     *
     * Order hasil konversi sample berhenti pada status Siap Produksi. Saat
     * mandor memulai, order masuk lantai produksi SEKALIGUS setiap komponen
     * artikelnya mendapat penjahit, sehingga tidak pernah ada order yang
     * berjalan tanpa pemilik komponen yang jelas. Rencana divalidasi lebih dulu,
     * lalu status dan penugasan ditulis dalam satu transaksi — rencana yang
     * ditolak tidak meninggalkan bekas apa pun pada order.
     *
     * Rencana ini menggantikan alokasi berbasis line sebagai penentu siapa
     * mengerjakan apa. Line melayani order lintas klien, sehingga keanggotaan
     * line tidak menunjukkan tanggung jawab per komponen; alokasi line tetap
     * berlaku untuk hal yang memang soal line, mis. penempatan mesin.
     *
     * Order tanpa artikel atau dengan artikel tanpa komponen tidak punya
     * rencana untuk diisi, sehingga daftar kosong adalah jawaban yang benar.
     *
     * @param  array<int, array{component_id: int, user_id: int}>  $componentAssignments
     */
    public function startProduction(Order $order, array $componentAssignments): Order
    {
        if ($order->status !== OrderStatus::READY_FOR_PRODUCTION) {
            throw InvalidOrderState::notReadyForProduction($order);
        }

        $components = $order->components();
        $plan = $this->validateComponentAssignments($order, $components, $componentAssignments);

        return DB::transaction(function () use ($order, $plan): Order {
            $order->transitionTo(OrderStatus::IN_PRODUCTION);

            foreach ($plan as $assignment) {
                OrderComponentAssignment::create([
                    'order_id' => $order->getKey(),
                    'component_id' => $assignment['component_id'],
                    'user_id' => $assignment['user_id'],
                    'assigned_at' => now(),
                ]);
            }

            return $order->refresh();
        });
    }

    /**
     * Memperbarui penugasan penjahit per komponen saat order sedang dalam proses produksi.
     *
     * @param  array<int, array{component_id: int, user_id: int}>  $componentAssignments
     */
    public function updateComponentAssignments(Order $order, array $componentAssignments): Order
    {
        if ($order->status !== OrderStatus::IN_PRODUCTION) {
            throw InvalidOrderState::notInProduction($order);
        }

        $components = $order->components();
        $plan = $this->validateComponentAssignments($order, $components, $componentAssignments);

        return DB::transaction(function () use ($order, $plan): Order {
            foreach ($plan as $assignment) {
                OrderComponentAssignment::updateOrCreate(
                    [
                        'order_id' => $order->getKey(),
                        'component_id' => $assignment['component_id'],
                    ],
                    [
                        'user_id' => $assignment['user_id'],
                        'assigned_at' => now(),
                    ]
                );
            }

            return $order->refresh();
        });
    }

    /**
     * Memeriksa rencana penjahit sebelum order berjalan.
     *
     * Aturannya: setiap komponen artikel mendapat tepat satu penjahit, dan
     * penjahitnya benar-benar berperan Penjahit — bukan sekadar pengguna mana
     * pun yang lolos dari dropdown.
     *
     * @param  Collection<int, Component>  $components
     * @param  array<int, array{component_id: int, user_id: int}>  $componentAssignments
     * @return array<int, array{component_id: int, user_id: int}>
     */
    private function validateComponentAssignments(Order $order, Collection $components, array $componentAssignments): array
    {
        $expectedIds = $components->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $submittedIds = [];

        foreach ($componentAssignments as $assignment) {
            $componentId = (int) $assignment['component_id'];

            if (! in_array($componentId, $expectedIds, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Komponen #%d bukan bagian dari artikel order %s.',
                    $componentId,
                    $order->order_number,
                ));
            }

            $tailor = User::find($assignment['user_id']);

            if ($tailor === null || ! $tailor->hasRoleSatisfying(fn (UserRole $role): bool => $role->canSubmitOutput())) {
                throw new InvalidArgumentException(sprintf(
                    'Pengguna #%s tidak berperan Penjahit, sehingga tidak dapat ditugaskan ke komponen.',
                    $assignment['user_id'],
                ));
            }

            $submittedIds[] = $componentId;
        }

        $missing = $components
            ->reject(fn (Component $component): bool => in_array((int) $component->getKey(), $submittedIds, true))
            ->pluck('name')
            ->all();

        if ($missing !== []) {
            throw IncompleteComponentAssignment::missingComponents($order, $missing);
        }

        if (count($submittedIds) !== count($expectedIds)) {
            throw IncompleteComponentAssignment::duplicatedComponent($order);
        }

        return array_map(fn (array $assignment): array => [
            'component_id' => (int) $assignment['component_id'],
            'user_id' => (int) $assignment['user_id'],
        ], array_values($componentAssignments));
    }

    /**
     * Menugaskan penjahit ke sebuah line dan opsional stasiun (FR-009, NFR-001).
     * Didelegasikan ke LineAllocationService untuk penutupan alokasi aktif sebelumnya secara otomatis.
     */
    public function assignOperator(ProductionLine $line, User $operator, ?string $station = null): LineAssignment
    {
        return app(LineAllocationService::class)->assignOperator($line, $operator, $station);
    }

    /**
     * Menutup penugasan dengan mengisi unassigned_at, bukan menghapusnya, agar
     * riwayat alokasi tetap dapat diaudit (FR-022).
     */
    public function unassignOperator(LineAssignment $assignment): LineAssignment
    {
        return app(LineAllocationService::class)->unassignOperator($assignment);
    }

    /**
     * Mencatat output penjahit untuk satu interval 30 menit (FR-011).
     *
     * Waktu yang diberikan dibulatkan ke batas interval terdekat supaya satu
     * penjahit hanya menghasilkan satu catatan per komponen per interval, sesuai
     * unique(user_id, component_id, interval_start).
     *
     * Line boleh kosong. Penugasan penjahit ditetapkan per komponen, sehingga
     * penjahit yang tidak sedang terdaftar di line mana pun tetap boleh
     * melaporkan hasil kerjanya; line hanya diisi bila memang diketahui.
     */
    public function submitOutput(
        Order $order,
        ?ProductionLine $line,
        User $operator,
        Component $component,
        int $quantity,
        DateTimeInterface $intervalAt,
        ?string $notes = null,
    ): OutputEntry {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Jumlah output minimal satu unit.');
        }

        // Validasi peran penjahit jika pengguna memiliki role terdaftar
        if ($operator->roles()->exists() && ! $operator->hasRoleSatisfying(fn (UserRole $role): bool => $role->canSubmitOutput())) {
            throw UnassignedOperatorException::notTailor($operator);
        }

        // Validasi penugasan komponen: jika order memiliki penugasan komponen spesifik,
        // hanya penjahit yang ditugaskan ke komponen tersebut yang boleh melaporkan output.
        if ($order->componentAssignments()->exists()) {
            $isAssignedToComponent = $order->componentAssignments()
                ->where('component_id', $component->getKey())
                ->where('user_id', $operator->getKey())
                ->exists();

            if (! $isAssignedToComponent) {
                throw UnassignedOperatorException::forComponent($operator, $component, $order);
            }
        }

        // Validasi penugasan line: jika line diberikan, pastikan operator tidak memiliki
        // alokasi line yang sudah ditutup atau alokasi aktif di line lain.
        if ($line !== null) {
            $hasLineHistory = LineAssignment::query()
                ->where('production_line_id', $line->getKey())
                ->where('user_id', $operator->getKey())
                ->exists();

            $hasActiveInLine = LineAssignment::query()
                ->where('production_line_id', $line->getKey())
                ->where('user_id', $operator->getKey())
                ->whereNull('unassigned_at')
                ->exists();

            if ($hasLineHistory && ! $hasActiveInLine) {
                throw UnassignedOperatorException::forLine($operator, $line);
            }

            $hasActiveInOtherLine = LineAssignment::query()
                ->where('production_line_id', '!=', $line->getKey())
                ->where('user_id', $operator->getKey())
                ->whereNull('unassigned_at')
                ->exists();

            if ($hasActiveInOtherLine && ! $hasActiveInLine) {
                throw UnassignedOperatorException::forLine($operator, $line);
            }
        }

        [$start, $end] = $this->calculateInterval($intervalAt);

        $existing = OutputEntry::query()
            ->where('user_id', $operator->getKey())
            ->where('component_id', $component->getKey())
            ->where('interval_start', $start)
            ->first();

        if ($existing !== null) {
            throw DuplicateOutputInterval::for($existing);
        }

        return OutputEntry::create([
            'client_id' => $order->client_id,
            'order_id' => $order->getKey(),
            'production_line_id' => $line?->getKey(),
            'user_id' => $operator->getKey(),
            'component_id' => $component->getKey(),
            'quantity' => $quantity,
            'interval_start' => $start,
            'interval_end' => $end,
            'status' => OutputStatus::SUBMITTED,
            'notes' => $notes,
        ]);
    }

    /**
     * Memverifikasi output sehingga layak masuk perhitungan payroll (FR-012, BR 4.5).
     *
     * Hanya mandor / kepala produksi yang berhak memverifikasi. Penjahit dilarang
     * memverifikasi pekerjaannya sendiri untuk menegakkan separasi tugas.
     */
    public function verifyOutput(OutputEntry $entry, User $verifier): OutputEntry
    {
        if ($entry->status !== OutputStatus::SUBMITTED) {
            throw InvalidOutputState::notAwaitingVerification($entry);
        }

        if (! $verifier->hasRoleSatisfying(fn (UserRole $role): bool => $role->canVerifyOutput())) {
            throw InvalidOutputState::unauthorizedVerifier();
        }

        if ((int) $entry->user_id === (int) $verifier->getKey()) {
            throw InvalidOutputState::selfVerificationForbidden($entry);
        }

        $entry->update([
            'status' => OutputStatus::VERIFIED,
            'verified_by' => $verifier->getKey(),
            'verified_at' => now(),
        ]);

        return $entry->refresh();
    }

    /**
     * Menolak output yang tidak memenuhi standar kualitas (FR-012, BR 4.5).
     *
     * Keputusan penolakan dicatat lengkap dengan alasan dan penanggung jawabnya
     * agar penjahit mengetahui koreksi yang diperlukan saat mengajukan ulang.
     */
    public function rejectOutput(OutputEntry $entry, ?string $reason = null, ?User $rejector = null): OutputEntry
    {
        if ($entry->status !== OutputStatus::SUBMITTED) {
            throw InvalidOutputState::notAwaitingVerification($entry);
        }

        $rejector = $rejector ?? (auth()->check() ? auth()->user() : null);

        if ($rejector !== null) {
            if (! $rejector->hasRoleSatisfying(fn (UserRole $role): bool => $role->canVerifyOutput())) {
                throw InvalidOutputState::unauthorizedVerifier();
            }

            if ((int) $entry->user_id === (int) $rejector->getKey()) {
                throw InvalidOutputState::selfVerificationForbidden($entry);
            }
        }

        $entry->update([
            'status' => OutputStatus::REJECTED,
            'rejection_reason' => $reason,
            'rejected_by' => $rejector?->getKey(),
            'notes' => $reason ?? $entry->notes,
        ]);

        return $entry->refresh();
    }

    /**
     * Mengajukan kembali output yang sebelumnya ditolak setelah dilakukan perbaikan (BR 4.5).
     *
     * Status kembali menjadi SUBMITTED sehingga mandor dapat meninjau ulang.
     */
    public function resubmitOutput(OutputEntry $entry, int $newQuantity, ?string $notes = null): OutputEntry
    {
        if ($entry->status !== OutputStatus::REJECTED) {
            throw InvalidOutputState::notRejected($entry);
        }

        if ($newQuantity < 1) {
            throw new InvalidArgumentException('Jumlah output minimal satu unit.');
        }

        $entry->update([
            'status' => OutputStatus::SUBMITTED,
            'quantity' => $newQuantity,
            'notes' => $notes ?? $entry->notes,
        ]);

        return $entry->refresh();
    }

    /**
     * Memverifikasi sekaligus seluruh output order yang masih menunggu (FR-012).
     *
     * Mandor yang sudah memeriksa satu order biasanya memutuskan untuk seluruh
     * barisnya, bukan baris per baris. Verifikasi massal dijalankan dalam satu
     * transaksi supaya tidak ada baris yang tertinggal separuh terverifikasi bila
     * satu baris gagal, dan aturan statusnya tetap dipinjam dari verifyOutput()
     * agar tidak ada dua definisi "boleh diverifikasi".
     *
     * @return int jumlah baris yang diverifikasi
     */
    public function verifyAllPendingOutputs(Order $order, User $verifier): int
    {
        return DB::transaction(function () use ($order, $verifier): int {
            $entries = $this->pendingOutputs($order);

            foreach ($entries as $entry) {
                $this->verifyOutput($entry, $verifier);
            }

            return $entries->count();
        });
    }

    /**
     * Output order yang masih menunggu verifikasi, terurut sesuai urutan
     * kejadiannya agar notifikasi dan pengujian memandang baris dalam urutan
     * yang sama.
     *
     * @return Collection<int, OutputEntry>
     */
    private function pendingOutputs(Order $order): Collection
    {
        return $order->outputEntries()
            ->where('status', OutputStatus::SUBMITTED->value)
            ->orderBy('interval_start')
            ->orderBy('id')
            ->get();
    }

    /**
     * Merakit output terverifikasi menjadi produk utuh (FR-013).
     *
     * Hanya output berstatus terverifikasi yang boleh menyusun perakitan, dan
     * seluruhnya harus milik order yang sama. Ordernya sendiri juga harus sudah
     * siap dirakit: tidak ada output yang menunggu verifikasi dan setiap komponen
     * artikelnya sudah punya output terverifikasi (Order::isReadyForAssembly()).
     *
     * @param  array<int, int>  $outputEntryIds
     */
    public function assemble(Order $order, array $outputEntryIds, int $quantity, ?string $notes = null): Assembly
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Jumlah hasil perakitan minimal satu unit.');
        }

        $entries = OutputEntry::query()->whereIn('id', $outputEntryIds)->get();

        if ($entries->isEmpty()) {
            throw new InvalidArgumentException('Pilih minimal satu output terverifikasi untuk dirakit.');
        }

        foreach ($entries as $entry) {
            if (! $entry->status->isPayrollEligible()) {
                throw InvalidOutputState::notVerified($entry);
            }

            if ((int) $entry->order_id !== (int) $order->getKey()) {
                throw new InvalidArgumentException(sprintf(
                    'Output #%d bukan milik order %s, sehingga tidak boleh dirakit ke order ini.',
                    $entry->getKey(),
                    $order->order_number,
                ));
            }
        }

        // Gerbang kesiapan order diperiksa setelah baris yang dipilih sendiri sah:
        // salah memilih output tetap dilaporkan sebagai galat output, bukan sebagai
        // order yang belum siap.
        $this->ensureReadyForAssembly($order);

        return DB::transaction(function () use ($order, $entries, $quantity, $notes): Assembly {
            $assembly = Assembly::create([
                'client_id' => $order->client_id,
                'order_id' => $order->getKey(),
                'quantity' => $quantity,
                'assembled_by' => auth()->id(),
                'assembled_at' => now(),
                'notes' => $notes,
            ]);

            $assembly->outputEntries()->sync($entries->pluck('id')->all());

            // Transisi ke IN_QC jika seluruh target pesanan telah selesai dirakit (Req 10 AC 6)
            $totalAssembled = (int) $order->assemblies()->sum('quantity');
            if ($order->status === OrderStatus::IN_PRODUCTION && $totalAssembled >= $order->quantity && $order->canTransitionTo(OrderStatus::IN_QC)) {
                $order->transitionTo(OrderStatus::IN_QC);
            }

            return $assembly->refresh();
        });
    }

    /**
     * Menegakkan syarat perakitan (FR-012 → FR-013).
     *
     * Tombol yang aktif di UI hanyalah kenyamanan; aturan yang sama harus berlaku
     * bagi pemanggil mana pun, termasuk pengujian dan perintah console. Karena itu
     * keputusannya diambil dari Order::isReadyForAssembly() — definisi tunggal yang
     * juga dipakai halaman detail order.
     */
    private function ensureReadyForAssembly(Order $order): void
    {
        if ($order->isReadyForAssembly()) {
            return;
        }

        // Penyebab yang paling cepat ditindaklanjuti dilaporkan lebih dulu:
        // verifikasi dulu, karena itulah tombol yang tersedia di halaman detail.
        // Sisa kekurangannya baru terlihat setelah verifikasi itu selesai.
        if ($order->hasPendingOutputVerification()) {
            throw AssemblyNotReady::awaitingVerification($order, $order->pendingOutputVerificationCount());
        }

        $missingComponents = $order->componentsWithoutVerifiedOutput();

        if ($missingComponents->isNotEmpty()) {
            throw AssemblyNotReady::missingVerifiedComponents($order, $missingComponents->pluck('name')->all());
        }

        throw AssemblyNotReady::noVerifiedOutput($order);
    }

    /**
     * Membulatkan waktu ke batas interval 30 menit (:00 atau :30) (Task 2.2, FR-011).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function calculateInterval(DateTimeInterface $time): array
    {
        $moment = Carbon::instance($time)->seconds(0);
        $minute = (int) $moment->format('i') < 30 ? 0 : 30;
        $start = $moment->copy()->setTime((int) $moment->format('H'), $minute, 0);

        return [$start, $start->copy()->addMinutes(30)];
    }

    /**
     * Alias privat untuk backward-compatibility internal.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function intervalBounds(DateTimeInterface $at): array
    {
        return $this->calculateInterval($at);
    }
}
