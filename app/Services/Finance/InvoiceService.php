<?php

namespace App\Services\Finance;

use App\Enums\InventoryState;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\OutputStatus;
use App\Enums\WorkScheme;
use App\Exceptions\InvalidInvoiceState;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OutputEntry;
use App\Models\OverheadAllocation;
use App\Models\RateCard;

/**
 * Satu-satunya jalur tulis untuk penerbitan dan pelunasan invoice klien
 * (matriks RBAC SRS, Finance).
 */
class InvoiceService
{
    /**
     * Menerbitkan invoice draf. Tanggal terbit yang sudah diisi pengguna
     * dipertahankan, hanya diisi otomatis bila masih kosong.
     */
    public function issue(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw InvalidInvoiceState::notDraft($invoice);
        }

        $invoice->update([
            'status' => InvoiceStatus::ISSUED,
            'issued_at' => $invoice->issued_at ?? now(),
            'issued_by' => auth()->id(),
        ]);

        return $invoice->refresh();
    }

    public function markPaid(Invoice $invoice): Invoice
    {
        if (! in_array($invoice->status, [InvoiceStatus::ISSUED, InvoiceStatus::PAYMENT_CONFIRMED], true)) {
            throw InvalidInvoiceState::notIssued($invoice);
        }

        $invoice->update(['status' => InvoiceStatus::PAID]);

        $order = $invoice->order;
        if ($order) {
            // Tandai order menjadi COMPLETED (Selesai Produksi) di semua role
            if ($order->status !== OrderStatus::COMPLETED && $order->status !== OrderStatus::CLOSED) {
                $order->update([
                    'status' => OrderStatus::COMPLETED,
                    'completed_at' => now(),
                ]);
            }

            // Tandai seluruh inventory items pesanan ini menjadi FINISHED (Barang Jadi)
            $order->inventoryItems()
                ->where('state', '!=', InventoryState::FINISHED->value)
                ->update(['state' => InventoryState::FINISHED]);
        }

        return $invoice->refresh();
    }

    /**
     * Konfirmasi pembayaran oleh klien dari portal.
     *
     * @param  array{payment_method?: string|null, payment_notes?: string|null, payment_proof_path?: string|null}  $data
     */
    public function confirmPayment(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status !== InvoiceStatus::ISSUED) {
            throw new \RuntimeException('Invoice tidak dalam status menunggu pembayaran.');
        }

        $invoice->update([
            'status' => InvoiceStatus::PAYMENT_CONFIRMED,
            'payment_confirmed_at' => now(),
            'payment_method' => $data['payment_method'] ?? null,
            'payment_notes' => $data['payment_notes'] ?? null,
            'payment_proof_path' => $data['payment_proof_path'] ?? null,
        ]);

        return $invoice->refresh();
    }

    /**
     * Hitung seluruh komponen tagihan untuk satu order:
     *   1. Ongkos Jahit = Σ(qty_verified × rate_komponen)
     *   2. Overhead     = overhead_per_unit × qty_order  (hanya SEMI_CMT & FULL_PACKAGE)
     *   3. PPN          = (sewing + overhead) × tax_percent / 100  (jika tax_percent > 0)
     *   4. Grand Total  = sewing + overhead + tax
     *
     * Return value menjadi sumber kebenaran tunggal yang dipakai form dan detail popup.
     *
     * @param  float  $taxPercent  persentase PPN yang sudah diinput Finance (0 = tidak ada pajak)
     * @return array{
     *   sewing_amount: float,
     *   overhead_amount: float,
     *   tax_amount: float,
     *   amount: float,
     *   unrated_entries: int,
     *   is_overhead_applicable: bool,
     *   overhead_per_unit: float,
     *   overhead_qty: int,
     *   overhead_period: string|null,
     *   work_scheme: string,
     *   breakdown: array<int, array{component: string, qty: int, rate: float, subtotal: float}>
     * }
     */
    public function calculateAmountForOrder(Order $order, float $taxPercent = 0.0): array
    {
        // ── 1. Ongkos Jahit ──────────────────────────────────────────────────
        $sewingResult = $this->calculateSewingCost($order);
        $sewingAmount = $sewingResult['sewing_amount'];

        // ── 2. Overhead (hanya untuk SEMI_CMT dan FULL_PACKAGE) ─────────────
        $overheadApplicable = $order->work_scheme !== WorkScheme::CMT;
        $overheadAmount = 0.0;
        $overheadPerUnit = 0.0;
        $overheadQty = $order->quantity ?? 0;
        $overheadPeriod = null;

        if ($overheadApplicable && $overheadQty > 0) {
            $allocation = $this->getOverheadAllocationForOrder($order);

            if ($allocation) {
                $overheadPerUnit = (float) $allocation->overhead_per_unit;
                $overheadAmount = round($overheadPerUnit * $overheadQty, 2);
                $overheadPeriod = $allocation->period_month->format('M Y');
            }
        }

        // ── 3. PPN ───────────────────────────────────────────────────────────
        $subtotalBeforeTax = round($sewingAmount + $overheadAmount, 2);
        $taxAmount = $taxPercent > 0
            ? round($subtotalBeforeTax * $taxPercent / 100, 2)
            : 0.0;

        // ── 4. Grand Total ───────────────────────────────────────────────────
        $grandTotal = round($subtotalBeforeTax + $taxAmount, 2);

        return [
            'sewing_amount' => $sewingAmount,
            'overhead_amount' => $overheadAmount,
            'tax_amount' => $taxAmount,
            'amount' => $grandTotal,
            'unrated_entries' => $sewingResult['unrated_entries'],
            'is_overhead_applicable' => $overheadApplicable,
            'overhead_per_unit' => $overheadPerUnit,
            'overhead_qty' => $overheadQty,
            'overhead_period' => $overheadPeriod,
            'work_scheme' => $order->work_scheme->getLabel(),
            'breakdown' => $sewingResult['breakdown'],
        ];
    }

    /**
     * Hitung biaya overhead saja untuk order tertentu.
     * Dipakai form saat hanya tax_percent yang berubah agar overhead tidak dihitung ulang.
     */
    public function recalculateWithTax(float $sewingAmount, float $overheadAmount, float $taxPercent): array
    {
        $subtotal = round($sewingAmount + $overheadAmount, 2);
        $taxAmount = $taxPercent > 0 ? round($subtotal * $taxPercent / 100, 2) : 0.0;
        $total = round($subtotal + $taxAmount, 2);

        return [
            'tax_amount' => $taxAmount,
            'amount' => $total,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Hitung ongkos jahit dari output entries terverifikasi × rate card.
     *
     * @return array{sewing_amount: float, unrated_entries: int, breakdown: array<int, array{component: string, qty: int, rate: float, subtotal: float}>}
     */
    private function calculateSewingCost(Order $order): array
    {
        $today = now()->toDateString();

        $verifiedEntries = OutputEntry::query()
            ->where('order_id', $order->id)
            ->where('status', OutputStatus::VERIFIED)
            ->with('component')
            ->get();

        if ($verifiedEntries->isEmpty()) {
            return ['sewing_amount' => 0.0, 'unrated_entries' => 0, 'breakdown' => []];
        }

        $componentIds = $verifiedEntries->pluck('component_id')->unique()->filter();

        $rateCards = RateCard::query()
            ->where('client_id', $order->client_id)
            ->whereIn('component_id', $componentIds)
            ->effectiveOn($today)
            ->get()
            ->keyBy('component_id');

        $sewingTotal = 0.0;
        $unratedEntries = 0;
        $breakdown = [];

        foreach ($verifiedEntries as $entry) {
            $rateCard = $rateCards->get($entry->component_id);

            if ($rateCard === null) {
                $unratedEntries++;

                continue;
            }

            $subtotal = $entry->quantity * (float) $rateCard->rate;
            $sewingTotal += $subtotal;

            $componentName = $entry->component?->name ?? "Komponen #{$entry->component_id}";

            if (isset($breakdown[$entry->component_id])) {
                $breakdown[$entry->component_id]['qty'] += $entry->quantity;
                $breakdown[$entry->component_id]['subtotal'] += $subtotal;
            } else {
                $breakdown[$entry->component_id] = [
                    'component' => $componentName,
                    'qty' => $entry->quantity,
                    'rate' => (float) $rateCard->rate,
                    'subtotal' => $subtotal,
                ];
            }
        }

        return [
            'sewing_amount' => round($sewingTotal, 2),
            'unrated_entries' => $unratedEntries,
            'breakdown' => array_values($breakdown),
        ];
    }

    /**
     * Ambil alokasi overhead untuk bulan pembuatan order (optimasi lookup).
     */
    private function getOverheadAllocationForOrder(Order $order): ?OverheadAllocation
    {
        $periodMonth = $order->created_at->startOfMonth()->toDateString();

        return OverheadAllocation::query()
            ->where('period_month', $periodMonth)
            ->first();
    }
}
