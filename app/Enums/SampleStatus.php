<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status siklus hidup sample, dari draf hingga disetujui untuk produksi massal.
 *
 * @see FR-003 — sample review and approval before mass-production order
 */
enum SampleStatus: string implements HasColor, HasLabel
{
    case DRAFT = 'draft';
    case IN_REVIEW = 'in_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf',
            self::IN_REVIEW => 'Menunggu Persetujuan',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::IN_REVIEW => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }

    /**
     * Hanya sample yang disetujui boleh dikonversi menjadi order produksi massal (FR-004).
     */
    public function isApprovable(): bool
    {
        return $this === self::IN_REVIEW;
    }

    public function canBeConvertedToOrder(): bool
    {
        return $this === self::APPROVED;
    }
}
