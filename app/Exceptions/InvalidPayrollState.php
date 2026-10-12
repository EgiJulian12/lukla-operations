<?php

namespace App\Exceptions;

use App\Models\PayrollPeriod;
use RuntimeException;

/**
 * Payroll tidak berada dalam keadaan yang memungkinkan aksi yang diminta
 * (FR-015).
 */
class InvalidPayrollState extends RuntimeException
{
    public static function locked(PayrollPeriod $period): self
    {
        return new self(sprintf(
            'Periode payroll %s sudah %s dan tidak dapat dihitung ulang.',
            $period->label,
            $period->status->getLabel(),
        ));
    }

    public static function notCalculated(PayrollPeriod $period): self
    {
        return new self(sprintf(
            'Periode payroll %s belum dihitung, sehingga belum dapat disetujui.',
            $period->label,
        ));
    }

    public static function notApproved(PayrollPeriod $period): self
    {
        return new self(sprintf(
            'Periode payroll %s belum disetujui, sehingga belum dapat ditandai dibayar.',
            $period->label,
        ));
    }
}
