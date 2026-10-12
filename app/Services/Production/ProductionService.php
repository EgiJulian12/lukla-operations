<?php

namespace App\Services\Production;

use App\Enums\OutputStatus;
use App\Exceptions\DuplicateOutputInterval;
use App\Exceptions\InvalidOutputState;
use App\Models\Assembly;
use App\Models\Component;
use App\Models\LineAssignment;
use App\Models\Order;
use App\Models\OutputEntry;
use App\Models\ProductionLine;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya jalur tulis untuk alokasi line, output 30 menit, verifikasi
 * mandor, dan perakitan (FR-009 sampai FR-013).
 *
 * Output yang sudah diverifikasi terkunci: verifikasi adalah gerbang menuju
 * payroll, dan setelah lewat gerbang itu penjahit tidak dapat mengubah catatan
 * (matriks RBAC, separasi tugas FR-012).
 */
class ProductionService
{
    /**
     * Menugaskan penjahit ke sebuah line (FR-009). Tanpa batas jumlah slot,
     * sehingga kapasitas line bersifat cair mengikuti volume order (NFR-001).
     */
    public function assignOperator(ProductionLine $line, User $operator): LineAssignment
    {
        return LineAssignment::create([
            'production_line_id' => $line->getKey(),
            'user_id' => $operator->getKey(),
            'assigned_at' => now(),
        ]);
    }

    /**
     * Menutup penugasan dengan mengisi unassigned_at, bukan menghapusnya, agar
     * riwayat alokasi tetap dapat diaudit.
     */
    public function unassignOperator(LineAssignment $assignment): LineAssignment
    {
        if ($assignment->isActive()) {
            $assignment->update(['unassigned_at' => now()]);
        }

        return $assignment->refresh();
    }

    /**
     * Mencatat output penjahit untuk satu interval 30 menit (FR-011).
     *
     * Waktu yang diberikan dibulatkan ke batas interval terdekat supaya satu
     * penjahit hanya menghasilkan satu catatan per komponen per interval, sesuai
     * unique(user_id, component_id, interval_start).
     */
    public function submitOutput(
        Order $order,
        ProductionLine $line,
        User $operator,
        Component $component,
        int $quantity,
        DateTimeInterface $intervalAt,
        ?string $notes = null,
    ): OutputEntry {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Jumlah output minimal satu unit.');
        }

        [$start, $end] = $this->intervalBounds($intervalAt);

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
            'production_line_id' => $line->getKey(),
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
     * Memverifikasi output sehingga layak masuk perhitungan payroll (FR-012).
     */
    public function verifyOutput(OutputEntry $entry, User $verifier): OutputEntry
    {
        if ($entry->status !== OutputStatus::SUBMITTED) {
            throw InvalidOutputState::notAwaitingVerification($entry);
        }

        $entry->update([
            'status' => OutputStatus::VERIFIED,
            'verified_by' => $verifier->getKey(),
            'verified_at' => now(),
        ]);

        return $entry->refresh();
    }

    public function rejectOutput(OutputEntry $entry, ?string $reason = null): OutputEntry
    {
        if ($entry->status !== OutputStatus::SUBMITTED) {
            throw InvalidOutputState::notAwaitingVerification($entry);
        }

        $entry->update([
            'status' => OutputStatus::REJECTED,
            'notes' => $reason ?? $entry->notes,
        ]);

        return $entry->refresh();
    }

    /**
     * Merakit output terverifikasi menjadi produk utuh (FR-013).
     *
     * Hanya output berstatus terverifikasi yang boleh menyusun perakitan, dan
     * seluruhnya harus milik order yang sama.
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

            return $assembly->refresh();
        });
    }

    /**
     * Membulatkan waktu ke batas interval 30 menit (:00 atau :30).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function intervalBounds(DateTimeInterface $at): array
    {
        $moment = Carbon::instance($at)->seconds(0);
        $minute = (int) $moment->format('i') < 30 ? 0 : 30;
        $start = $moment->copy()->setTime((int) $moment->format('H'), $minute, 0);

        return [$start, $start->copy()->addMinutes(30)];
    }
}
