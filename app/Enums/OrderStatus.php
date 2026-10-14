<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status order, dari permintaan klien hingga selesai.
 *
 * Tiga status pertama (REQUESTED, QUOTED, QUOTE_APPROVED) adalah fase pra-produksi
 * saat belum ada bahan maupun sample; sisanya adalah siklus produksi massal.
 */
enum OrderStatus: string implements HasColor, HasLabel
{
    case REQUESTED = 'requested';
    case QUOTED = 'quoted';
    case QUOTE_APPROVED = 'quote_approved';
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case READY_FOR_PRODUCTION = 'ready_for_production';
    case IN_PRODUCTION = 'in_production';
    case IN_QC = 'in_qc';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::REQUESTED => 'Permintaan Klien',
            self::QUOTED => 'Menunggu Persetujuan Klien',
            self::QUOTE_APPROVED => 'Penawaran Disetujui',
            self::DRAFT => 'Draf',
            self::CONFIRMED => 'Dikonfirmasi',
            self::READY_FOR_PRODUCTION => 'Siap Produksi',
            self::IN_PRODUCTION => 'Dalam Produksi',
            self::IN_QC => 'Dalam QC',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::REQUESTED => 'gray',
            self::QUOTED => 'warning',
            self::QUOTE_APPROVED => 'info',
            self::DRAFT => 'gray',
            self::CONFIRMED => 'info',
            self::READY_FOR_PRODUCTION => 'success',
            self::IN_PRODUCTION => 'warning',
            self::IN_QC => 'primary',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    /**
     * Order yang sudah dibatalkan atau selesai tidak boleh diubah lagi —
     * mendukung aturan "locked transaction history" pada matriks RBAC.
     */
    public function isLocked(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }

    /**
     * Fase sebelum produksi: belum ada bahan, output, maupun sample.
     * Halaman yang menampilkan progres produksi harus mengecualikan status ini
     * agar permintaan klien tidak muncul sebagai order yang sedang dikerjakan.
     */
    public function isPreProduction(): bool
    {
        return in_array($this, [self::REQUESTED, self::QUOTED, self::QUOTE_APPROVED], true);
    }

    public function needsQuotation(): bool
    {
        return $this === self::REQUESTED;
    }

    public function awaitingClientApproval(): bool
    {
        return $this === self::QUOTED;
    }

    public function isQuoteApproved(): bool
    {
        return $this === self::QUOTE_APPROVED;
    }
}
