<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status PO bahan — dibuat lalu disetujui (matriks RBAC SRS, Finance).
 */
enum PurchaseOrderStatus: string implements HasColor, HasLabel
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf',
            self::APPROVED => 'Disetujui',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::APPROVED => 'success',
        };
    }

    public function isApprovable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * PO yang sudah disetujui terkunci supaya pesanan ke pemasok tidak berubah
     * diam-diam setelah persetujuan (NFR-005).
     */
    public function isLocked(): bool
    {
        return $this === self::APPROVED;
    }
}
