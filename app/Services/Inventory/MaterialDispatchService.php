<?php

namespace App\Services\Inventory;

use App\Enums\DispatchStatus;
use App\Enums\InventoryState;
use App\Enums\MovementType;
use App\Exceptions\InsufficientStock;
use App\Models\InventoryItem;
use App\Models\MaterialDispatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service pengelola alur pengantaran bahan dari gudang ke bagian produksi / cutting (FR-012, Track D).
 */
class MaterialDispatchService
{
    public function __construct(
        protected InventoryService $inventoryService,
    ) {}

    /**
     * Membuat pengajuan pengiriman bahan ke produksi (status: REQUESTED).
     */
    public function createRequest(
        InventoryItem $item,
        float $quantity,
        User|int $requestedBy,
        ?string $notes = null,
    ): MaterialDispatch {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Jumlah bahan yang diajukan harus lebih besar dari nol.');
        }

        if ($quantity > (float) $item->quantity) {
            throw new InsufficientStock($item, $quantity, (float) $item->quantity);
        }

        $requesterId = $requestedBy instanceof User ? $requestedBy->getKey() : $requestedBy;

        return DB::transaction(function () use ($item, $quantity, $requesterId, $notes): MaterialDispatch {
            $dispatchNumber = sprintf(
                'DSP-%s-%04d',
                date('Ymd'),
                MaterialDispatch::whereDate('created_at', today())->count() + 1,
            );

            return MaterialDispatch::create([
                'dispatch_number' => $dispatchNumber,
                'client_id' => $item->client_id,
                'order_id' => $item->order_id,
                'inventory_item_id' => $item->getKey(),
                'material_id' => $item->material_id,
                'quantity' => $quantity,
                'uom' => $item->uom,
                'status' => DispatchStatus::REQUESTED,
                'notes' => $notes,
                'requested_by' => $requesterId,
                'requested_at' => now(),
            ]);
        });
    }

    /**
     * Menyetujui pengajuan pengiriman bahan (status: APPROVED).
     */
    public function approve(MaterialDispatch $dispatch, User|int $approvedBy): MaterialDispatch
    {
        if ($dispatch->status !== DispatchStatus::REQUESTED) {
            throw new InvalidArgumentException('Hanya pengajuan dengan status REQUESTED yang dapat disetujui.');
        }

        $approverId = $approvedBy instanceof User ? $approvedBy->getKey() : $approvedBy;

        $dispatch->update([
            'status' => DispatchStatus::APPROVED,
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        return $dispatch->fresh();
    }

    /**
     * Menolak pengajuan pengiriman bahan dengan alasan (status: REJECTED).
     */
    public function reject(MaterialDispatch $dispatch, User|int $rejectedBy, string $reason): MaterialDispatch
    {
        if ($dispatch->status !== DispatchStatus::REQUESTED) {
            throw new InvalidArgumentException('Hanya pengajuan dengan status REQUESTED yang dapat ditolak.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan penolakan pengiriman bahan wajib diisi.');
        }

        $rejectorId = $rejectedBy instanceof User ? $rejectedBy->getKey() : $rejectedBy;

        $dispatch->update([
            'status' => DispatchStatus::REJECTED,
            'approved_by' => $rejectorId,
            'rejection_reason' => $reason,
        ]);

        return $dispatch->fresh();
    }

    /**
     * Mengirimkan bahan fisik dari gudang (status: DISPATCHED).
     */
    public function dispatch(MaterialDispatch $dispatch, User|int $dispatchedBy): MaterialDispatch
    {
        if ($dispatch->status !== DispatchStatus::APPROVED) {
            throw new InvalidArgumentException('Hanya pengiriman yang telah disetujui yang dapat dikirim.');
        }

        $dispatcherId = $dispatchedBy instanceof User ? $dispatchedBy->getKey() : $dispatchedBy;

        $dispatch->update([
            'status' => DispatchStatus::DISPATCHED,
            'dispatched_by' => $dispatcherId,
            'dispatched_at' => now(),
        ]);

        return $dispatch->fresh();
    }

    /**
     * Menandai bahan sedang dalam perjalanan / antrian tunggu (status: PENDING_DELIVERY).
     */
    public function markPendingDelivery(MaterialDispatch $dispatch): MaterialDispatch
    {
        if (! in_array($dispatch->status, [DispatchStatus::APPROVED, DispatchStatus::DISPATCHED], true)) {
            throw new InvalidArgumentException('Status tidak valid untuk berpindah ke PENDING_DELIVERY.');
        }

        $dispatch->update([
            'status' => DispatchStatus::PENDING_DELIVERY,
        ]);

        return $dispatch->fresh();
    }

    /**
     * Mengonfirmasi bahwa bahan telah diterima fisik oleh bagian produksi/cutting (status: RECEIVED).
     */
    public function confirmReceived(MaterialDispatch $dispatch, User|int $receivedBy): MaterialDispatch
    {
        if (! in_array($dispatch->status, [DispatchStatus::DISPATCHED, DispatchStatus::PENDING_DELIVERY], true)) {
            throw new InvalidArgumentException('Hanya bahan yang telah dikirim yang dapat dikonfirmasi penerimaannya.');
        }

        $receiverId = $receivedBy instanceof User ? $receivedBy->getKey() : $receivedBy;

        $dispatch->update([
            'status' => DispatchStatus::RECEIVED,
            'received_by' => $receiverId,
            'received_at' => now(),
        ]);

        if ($dispatch->inventoryItem && $dispatch->inventoryItem->state === InventoryState::RAW) {
            $dispatch->inventoryItem->update(['state' => InventoryState::WIP]);
        }

        return $dispatch->fresh();
    }

    /**
     * Menempatkan bahan pada antrian meja cutting (status: IDLE).
     */
    public function setIdleInQueue(MaterialDispatch $dispatch): MaterialDispatch
    {
        if ($dispatch->status !== DispatchStatus::RECEIVED) {
            throw new InvalidArgumentException('Hanya bahan yang telah diterima yang dapat ditempatkan di antrian idle.');
        }

        $dispatch->update([
            'status' => DispatchStatus::IDLE,
        ]);

        return $dispatch->fresh();
    }

    /**
     * Memulai proses pemotongan bahan di cutting (status: IN_CUTTING).
     */
    public function startCutting(MaterialDispatch $dispatch): MaterialDispatch
    {
        if (! in_array($dispatch->status, [DispatchStatus::RECEIVED, DispatchStatus::IDLE], true)) {
            throw new InvalidArgumentException('Hanya bahan yang sudah diterima atau di antrian idle yang dapat mulai dipotong.');
        }

        $dispatch->update([
            'status' => DispatchStatus::IN_CUTTING,
            'cutting_started_at' => now(),
        ]);

        return $dispatch->fresh();
    }

    /**
     * Menyelesaikan pemotongan bahan (status: COMPLETED), mencatat pengeluaran stok,
     * mentransisikan state inventory item ke WIP, dan mencatat scrap sisa potongan jika ada.
     */
    public function completeCutting(
        MaterialDispatch $dispatch,
        float $usedQuantity,
        float $scrapQuantity = 0,
        ?string $wasteCategory = null,
        ?string $wasteNotes = null,
    ): MaterialDispatch {
        if ($dispatch->status !== DispatchStatus::IN_CUTTING) {
            throw new InvalidArgumentException('Hanya pengiriman dengan status IN_CUTTING yang dapat diselesaikan pemotongannya.');
        }

        if ($usedQuantity <= 0) {
            throw new InvalidArgumentException('Jumlah bahan yang digunakan harus lebih dari nol.');
        }

        $totalIssued = $usedQuantity + $scrapQuantity;

        return DB::transaction(function () use ($dispatch, $totalIssued, $scrapQuantity, $wasteCategory, $wasteNotes): MaterialDispatch {
            $item = $dispatch->inventoryItem;
            $order = $dispatch->order;

            // 1. Catat pengeluaran stok fisik gudang ke cutting
            $this->inventoryService->recordMovement(
                item: $item,
                type: MovementType::ISSUE_TO_CUTTING,
                quantity: $totalIssued,
                destination: $order,
                reference: "Dispatch #{$dispatch->dispatch_number}",
                notes: 'Pengeluaran bahan ke meja cutting',
            );

            // 2. Transisi state item ke WIP bila saat ini masih RAW
            if ($item->fresh()->state === InventoryState::RAW) {
                $this->inventoryService->transitionState($item, InventoryState::WIP, $order);
            }

            // 3. Catat sisa potongan (waste/scrap) jika ada
            if ($scrapQuantity > 0) {
                $this->inventoryService->recordWaste(
                    order: $order,
                    material: $dispatch->material,
                    quantity: $scrapQuantity,
                    category: $wasteCategory ?: 'Sisa Potongan Pola Cutting',
                    notes: $wasteNotes ?: "Scrap pemotongan dari Dispatch #{$dispatch->dispatch_number}",
                );
            }

            $dispatch->update([
                'status' => DispatchStatus::COMPLETED,
                'completed_at' => now(),
            ]);

            return $dispatch->fresh();
        });
    }
}
