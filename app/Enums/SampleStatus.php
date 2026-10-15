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
    case REVISED = 'revised';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf',
            self::IN_REVIEW => 'Menunggu Persetujuan',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::REVISED => 'Telah Direvisi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::IN_REVIEW => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::REVISED => 'gray',
        };
    }

    /**
     * Hanya revisi yang berstatus draf yang dapat diubah atau ditambahkan time study.
     */
    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Revisi yang sedang ditinjau atau ditolak dapat dibuatkan revisi berikutnya (cloning).
     */
    public function canBeRevised(): bool
    {
        return in_array($this, [self::IN_REVIEW, self::REJECTED], true);
    }

    /**
     * Hanya sample yang menunggu persetujuan yang boleh disetujui/ditolak (FR-003).
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
