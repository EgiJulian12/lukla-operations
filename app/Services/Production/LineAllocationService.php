<?php

namespace App\Services\Production;

use App\Enums\UserRole;
use App\Exceptions\UnassignedOperatorException;
use App\Models\LineAssignment;
use App\Models\ProductionLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mengelola alokasi dinamis penjahit ke lini produksi dan rotasi stasiun (FR-009, NFR-001).
 *
 * Kapasitas lini bersifat cair tanpa batas slot kaku mengikuti volume order.
 * Setiap perpindahan stasiun atau lini menutup penugasan aktif sebelumnya
 * dengan unassigned_at (bukan hard delete) agar riwayat tetap dapat diaudit (FR-022).
 */
class LineAllocationService
{
    /**
     * Menugaskan operator ke suatu line dan opsional nomor stasiun kerja (FR-009).
     * Jika operator sebelumnya memiliki alokasi aktif di lini lain/sama, alokasi lama ditutup otomatis.
     */
    public function assignOperator(ProductionLine $line, User $operator, ?string $station = null): LineAssignment
    {
        if ($operator->roles()->exists() && ! $operator->hasRoleSatisfying(fn (UserRole $role): bool => $role->canSubmitOutput())) {
            throw UnassignedOperatorException::notTailor($operator);
        }

        return DB::transaction(function () use ($line, $operator, $station): LineAssignment {
            // Tutup alokasi aktif sebelumnya untuk operator ini (rotasi dinamis)
            LineAssignment::query()
                ->where('user_id', $operator->getKey())
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);

            return LineAssignment::create([
                'production_line_id' => $line->getKey(),
                'user_id' => $operator->getKey(),
                'station_number' => $station,
                'assigned_at' => now(),
            ]);
        });
    }

    /**
     * Merotasi penjahit ke stasiun kerja baru di lini yang sama (FR-009).
     * Menutup penugasan saat ini dan membuka penugasan stasiun baru.
     */
    public function rotateStation(LineAssignment $assignment, string $newStation): LineAssignment
    {
        if (! $assignment->isActive()) {
            throw new InvalidArgumentException(sprintf(
                'Penugasan operator #%d di line #%d sudah tidak aktif, tidak dapat dirotasi.',
                $assignment->user_id,
                $assignment->production_line_id,
            ));
        }

        if ($assignment->station_number === $newStation) {
            return $assignment;
        }

        return DB::transaction(function () use ($assignment, $newStation): LineAssignment {
            $assignment->update(['unassigned_at' => now()]);

            return LineAssignment::create([
                'production_line_id' => $assignment->production_line_id,
                'user_id' => $assignment->user_id,
                'station_number' => $newStation,
                'assigned_at' => now(),
            ]);
        });
    }

    /**
     * Menutup penugasan operator dari lini kerja tanpa menghapus record (audit trail FR-022).
     */
    public function unassignOperator(LineAssignment $assignment): LineAssignment
    {
        if ($assignment->isActive()) {
            $assignment->update(['unassigned_at' => now()]);
        }

        return $assignment->refresh();
    }

    /**
     * Mengambil alokasi aktif saat ini untuk operator tertentu.
     */
    public function getActiveAssignment(User $operator): ?LineAssignment
    {
        return LineAssignment::query()
            ->where('user_id', $operator->getKey())
            ->whereNull('unassigned_at')
            ->with('productionLine')
            ->latest('assigned_at')
            ->first();
    }

    /**
     * Mengambil seluruh penugasan operator yang aktif pada lini tertentu (NFR-001).
     *
     * @return Collection<int, LineAssignment>
     */
    public function getActiveLineAssignments(ProductionLine $line): Collection
    {
        return LineAssignment::query()
            ->where('production_line_id', $line->getKey())
            ->whereNull('unassigned_at')
            ->with('user')
            ->orderBy('station_number')
            ->get();
    }
}
