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
    case CLOSED = 'closed';
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
            self::CLOSED => 'Ditutup',
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
            self::CLOSED => 'gray',
            self::CANCELLED => 'danger',
        };
    }

    /**
     * Order yang sudah dibatalkan, selesai, atau ditutup tidak boleh diubah lagi —
     * mendukung aturan "locked transaction history" pada matriks RBAC (FR-021).
     */
    public function isLocked(): bool
    {
        return in_array($this, [self::COMPLETED, self::CLOSED, self::CANCELLED], true);
    }

    /**
     * Memeriksa apakah transisi dari status ini ke status target sah sesuai alur lifecycle order.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return match ($this) {
            self::REQUESTED => in_array($target, [self::QUOTED, self::CANCELLED], true),
            self::QUOTED => in_array($target, [self::QUOTE_APPROVED, self::REQUESTED, self::CANCELLED], true),
            self::QUOTE_APPROVED => in_array($target, [self::CONFIRMED, self::DRAFT, self::READY_FOR_PRODUCTION, self::CANCELLED], true),
            self::DRAFT => in_array($target, [self::CONFIRMED, self::READY_FOR_PRODUCTION, self::CANCELLED], true),
            self::CONFIRMED => in_array($target, [self::READY_FOR_PRODUCTION, self::CANCELLED], true),
            self::READY_FOR_PRODUCTION => in_array($target, [self::IN_PRODUCTION, self::CANCELLED], true),
            self::IN_PRODUCTION => in_array($target, [self::IN_QC, self::CANCELLED], true),
            self::IN_QC => in_array($target, [self::COMPLETED, self::IN_PRODUCTION, self::CANCELLED], true),
            self::COMPLETED => in_array($target, [self::CLOSED], true),
            self::CLOSED, self::CANCELLED => false,
        };
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
