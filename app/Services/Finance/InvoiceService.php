<?php

namespace App\Services\Finance;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvalidInvoiceState;
use App\Models\Invoice;

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
        if ($invoice->status !== InvoiceStatus::ISSUED) {
            throw InvalidInvoiceState::notIssued($invoice);
        }

        $invoice->update(['status' => InvoiceStatus::PAID]);

        return $invoice->refresh();
    }
}
