<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status verifikasi output 30 menit (FR-012). Output hanya masuk perhitungan
 * payroll setelah diverifikasi mandor.
 */
enum OutputStatus: string implements HasColor, HasLabel
{
    case SUBMITTED = 'submitted';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::SUBMITTED => 'Menunggu Verifikasi',
            self::VERIFIED => 'Terverifikasi',
            self::REJECTED => 'Ditolak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SUBMITTED => 'warning',
            self::VERIFIED => 'success',
            self::REJECTED => 'danger',
        };
    }

    /**
     * Hanya output terverifikasi yang boleh masuk perhitungan borongan (FR-015).
     */
    public function isPayrollEligible(): bool
    {
        return $this === self::VERIFIED;
    }

    /**
     * Output yang sudah diverifikasi terkunci — penjahit tidak dapat
     * mengeditnya kembali (matriks RBAC, Penjahit).
     */
    public function isLocked(): bool
    {
        return $this === self::VERIFIED;
    }
}
