<?php

namespace App\Exceptions;

use App\Models\Invoice;
use RuntimeException;

/**
 * Invoice tidak berada dalam keadaan yang memungkinkan aksi yang diminta
 * (matriks RBAC SRS, Finance).
 */
class InvalidInvoiceState extends RuntimeException
{
    public static function notDraft(Invoice $invoice): self
    {
        return new self(sprintf(
            'Invoice %s sudah %s, sehingga tidak dapat diterbitkan lagi.',
            $invoice->invoice_number,
            $invoice->status->getLabel(),
        ));
    }

    public static function notIssued(Invoice $invoice): self
    {
        return new self(sprintf(
            'Invoice %s belum terbit, sehingga belum dapat ditandai lunas.',
            $invoice->invoice_number,
        ));
    }
}
