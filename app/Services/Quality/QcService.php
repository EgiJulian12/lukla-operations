<?php

namespace App\Services\Quality;

use App\Enums\OrderStatus;
use App\Enums\QcResult;
use App\Exceptions\InvalidQcInspection;
use App\Models\Assembly;
use App\Models\DefectCategory;
use App\Models\QcRecord;
use App\Models\User;
use App\Models\WasteRecord;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya jalur tulis untuk hasil inspeksi QC (FR-014).
 *
 * Keputusan QC adalah catatan peristiwa: hanya boleh dibuat, tidak disunting
 * maupun dihapus. Hanya unit LOLOS yang membentuk pool bonus QC (FR-017).
 * - PASS: Lolos ke Finished Goods, terhitung ke bonus pool.
 * - REPAIR: Wajib defect category, tiket repair dibuat via RepairManager.
 * - SCRAP: Wajib defect category, otomatis tercatat ke waste_records (FR-008).
 */
class QcService
{
    public function __construct(
        protected ?RepairManager $repairManager = null,
    ) {
        $this->repairManager ??= app(RepairManager::class);
    }

    /**
     * Mencatat hasil inspeksi sebuah perakitan.
     *
     * Klien dan order diambil dari perakitan, bukan dari input pemanggil,
     * sehingga hasil QC selalu teratribusi ke pemilik yang benar.
     */
    public function recordInspection(
        Assembly $assembly,
        QcResult $result,
        int $quantity = 1,
        ?DefectCategory $defectCategory = null,
        ?string $notes = null,
        ?User $inspector = null,
    ): QcRecord {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Jumlah unit yang diperiksa minimal satu.');
        }

        if ($result->requiresDefectCategory() && $defectCategory === null) {
            throw InvalidQcInspection::missingDefectCategory($result);
        }

        return DB::transaction(function () use ($assembly, $result, $quantity, $defectCategory, $notes, $inspector): QcRecord {
            $record = QcRecord::create([
                'client_id' => $assembly->client_id,
                'order_id' => $assembly->order_id,
                'assembly_id' => $assembly->getKey(),
                'result' => $result,
                'defect_category_id' => $defectCategory?->getKey(),
                'quantity' => $quantity,
                'inspected_by' => $inspector?->getKey() ?? auth()->id(),
                'inspected_at' => now(),
                'notes' => $notes,
            ]);

            if ($result === QcResult::REPAIR) {
                $this->repairManager->createFromQc($record);
            }

            if ($result === QcResult::SCRAP) {
                WasteRecord::create([
                    'client_id' => $assembly->client_id,
                    'order_id' => $assembly->order_id,
                    'quantity' => $quantity,
                    'uom' => 'pcs',
                    'category' => $defectCategory?->name ?? 'QC Scrap',
                    'notes' => $notes ?? 'Scrapped during QC inspection',
                    'recorded_by' => $inspector?->getKey() ?? auth()->id(),
                ]);
            }

            // Update status lifecycle order:
            // 1. Jika masih IN_PRODUCTION, transisi ke IN_QC saat inspeksi QC berjalan (Req 10 AC 6)
            $order = $assembly->order;
            if ($order && $order->status === OrderStatus::IN_PRODUCTION && $order->canTransitionTo(OrderStatus::IN_QC)) {
                $order->transitionTo(OrderStatus::IN_QC);
            }

            // 2. Jika 100% unit pesanan telah berstatus PASS, transisi ke COMPLETED (Req 10 AC 7)
            if ($order && $order->status === OrderStatus::IN_QC && $order->qcPassedQuantity() >= $order->quantity && $order->canTransitionTo(OrderStatus::COMPLETED)) {
                $order->transitionTo(OrderStatus::COMPLETED);
            }

            return $record;
        });
    }
}
