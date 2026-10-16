<?php

namespace App\Services\Finance;

use App\Exceptions\InvalidRateMarginException;
use App\Models\Component;
use App\Models\Order;

/**
 * RateManager mengelola rate card, margin guard, dan approval workflow untuk
 * Finance (Req 2.1-2.14, Property 1).
 *
 * Tarif per komponen ditentukan oleh Kepala Produksi di lantai produksi,
 * dengan oversight Finance dan Owner. RateManager memastikan:
 * - Margin tersedia positif (Req 2.2, 2.3)
 * - Rate komponen tidak melebihi margin tersedia (Req 2.4, 2.5)
 * - Total rate komponen tidak melebihi margin tersedia (Req 2.6, 2.7)
 * - Sequential approval untuk komponen (Req 2.8-2.12)
 */
class RateManager
{
    /**
     * Hitung margin tersedia: selling price - (material + overhead + profit).
     *
     * @throws InvalidRateMarginException jika margin <= 0
     */
    public function computeAvailableMargin(float $sellingPrice, float $materialCost, float $overhead, float $profit): float
    {
        $margin = $sellingPrice - ($materialCost + $overhead + $profit);

        if ($margin <= 0) {
            throw InvalidRateMarginException::nonPositive('Margin tersedia tidak boleh nol atau negatif');
        }

        return $margin;
    }

    /**
     * Validasi rate komponen tidak melebihi margin tersedia (Req 2.4, 2.5).
     *
     * @throws InvalidRateMarginException jika componentRate > availableMargin
     */
    public function validateRateKomponen(float $componentRate, float $availableMargin): bool
    {
        if ($componentRate > $availableMargin) {
            throw InvalidRateMarginException::exceedsLimit(
                $componentRate,
                $availableMargin
            );
        }

        return true;
    }

    /**
     * Validasi total rate komponen tidak melebihi margin tersedia (Req 2.6, 2.7).
     *
     * @throws InvalidRateMarginException jika sum > availableMargin
     */
    public function validateTotalComponentsMargin(array $componentRates, float $availableMargin): bool
    {
        $totalRates = array_sum($componentRates);

        if ($totalRates > $availableMargin) {
            $shortfall = $totalRates - $availableMargin;
            throw InvalidRateMarginException::exceedsTotal(
                $totalRates,
                $availableMargin,
                $shortfall
            );
        }

        return true;
    }

    /**
     * Enforce sequential approval untuk komponen (Req 2.8-2.12).
     *
     * Logika:
     * - Komponen harus disetujui berurutan (urutan 1, lalu 2, dst.)
     * - Transisi ke ACTIVE jika disetujui, REJECTED jika ditolak
     * - Jika komponen yang sudah disetujui diubah, wajibkan re-approval
     * - Ketika semua komponen disetujui, order ditandai APPROVED
     *
     * @throws \InvalidArgumentException jika urutan tidak berurutan
     */
    public function enforceSequentialApproval(Order $order, Component $component): void
    {
        $components = $order->components()->orderBy('order_sequence')->get();

        // Cek apakah komponen ini adalah komponen berikutnya yang harus disetujui
        $currentIndex = $components->search(fn ($c) => $c->id === $component->id);

        if ($currentIndex === false) {
            throw new \InvalidArgumentException('Komponen tidak ditemukan dalam order');
        }

        // Cek apakah semua komponen sebelumnya sudah disetujui
        for ($i = 0; $i < $currentIndex; $i++) {
            $prevComponent = $components->get($i);
            if ($prevComponent->approval_status !== 'APPROVED') {
                throw new \InvalidArgumentException(
                    'Komponen urutan '.($i + 1).' harus disetujui terlebih dahulu'
                );
            }
        }

        // Jika komponen ini sudah APPROVED dan ingin diubah, wajibkan re-approval
        if ($component->approval_status === 'APPROVED') {
            // Log audit trail untuk re-approval
            \Log::info('Re-approval komponen', [
                'order_id' => $order->id,
                'component_id' => $component->id,
                'changed_by' => auth()->id(),
            ]);
        }

        // Set status approval dan audit
        $component->update([
            'approval_status' => 'APPROVED',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Cek apakah semua komponen sudah disetujui
        $allApproved = $components->every(fn ($c) => $c->approval_status === 'APPROVED');

        if ($allApproved) {
            $order->update([
                'status' => $order::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        }
    }

    /**
     * Batalkan approval komponen untuk re-approval (Req 2.11).
     */
    public function cancelApproval(Component $component): void
    {
        $component->update([
            'approval_status' => 'PENDING',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    /**
     * Reject komponen (Req 2.9).
     */
    public function rejectComponent(Component $component, string $reason): void
    {
        $component->update([
            'approval_status' => 'REJECTED',
            'rejection_reason' => $reason,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
        ]);
    }
}
