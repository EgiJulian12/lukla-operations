<?php

namespace App\Services\Quality;

use App\Enums\RepairStatus;
use App\Models\QcRecord;
use App\Models\Repair;
use App\Models\User;

/**
 * Service untuk alur kerja perbaikan (Repair) barang cacat QC (BR 4.6, FR-014).
 */
class RepairManager
{
    /**
     * Membuat tiket repair dari hasil inspeksi QC.
     */
    public function createFromQc(QcRecord $qc, ?User $repairer = null): Repair
    {
        return Repair::create([
            'qc_record_id' => $qc->getKey(),
            'assigned_repairer_id' => $repairer?->getKey(),
            'status' => $repairer ? RepairStatus::IN_REPAIR : RepairStatus::PENDING,
            'completed_at' => null,
            'notes' => $qc->notes,
        ]);
    }

    /**
     * Menugaskan teknisi/penjahit untuk mengerjakan perbaikan.
     */
    public function assignRepairer(Repair $repair, User $repairer, ?string $notes = null): Repair
    {
        $data = [
            'assigned_repairer_id' => $repairer->getKey(),
            'status' => RepairStatus::IN_REPAIR,
        ];

        if ($notes !== null) {
            $data['notes'] = $repair->notes ? $repair->notes."\n".$notes : $notes;
        }

        $repair->update($data);

        return $repair->refresh();
    }

    /**
     * Menandai repair telah selesai dilakukan oleh kepala produksi/tim perbaikan.
     * Unit berpindah ke status 'Siap Dicek Ulang' (COMPLETED).
     */
    public function markCompleted(Repair $repair, ?string $notes = null): Repair
    {
        $data = [
            'status' => RepairStatus::COMPLETED,
            'completed_at' => now(),
        ];

        if ($notes !== null) {
            $data['notes'] = $repair->notes ? $repair->notes."\n".$notes : $notes;
        }

        $repair->update($data);

        return $repair->refresh();
    }

    /**
     * Menandai repair telah berhasil diverifikasi dan lolos QC ulang (VERIFIED).
     */
    public function markVerified(Repair $repair): Repair
    {
        $repair->update([
            'status' => RepairStatus::VERIFIED,
        ]);

        return $repair->refresh();
    }
}
