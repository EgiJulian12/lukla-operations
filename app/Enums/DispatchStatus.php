<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status alur pelacakan pengiriman bahan dari gudang ke bagian produksi / cutting.
 */
enum DispatchStatus: string implements HasColor, HasLabel
{
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case DISPATCHED = 'dispatched';
    case PENDING_DELIVERY = 'pending_delivery';
    case RECEIVED = 'received';
    case IDLE = 'idle';
    case IN_CUTTING = 'in_cutting';
    case COMPLETED = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::REQUESTED => 'Pengajuan',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::DISPATCHED => 'Dikirim',
            self::PENDING_DELIVERY => 'Pending (Menunggu Sampai)',
            self::RECEIVED => 'Diterima di Produksi',
            self::IDLE => 'Idle (Antrian Meja Potong)',
            self::IN_CUTTING => 'Sedang di Cutting',
            self::COMPLETED => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::REQUESTED => 'gray',
            self::APPROVED => 'info',
            self::REJECTED => 'danger',
            self::DISPATCHED => 'primary',
            self::PENDING_DELIVERY => 'warning',
            self::RECEIVED => 'info',
            self::IDLE => 'gray',
            self::IN_CUTTING => 'warning',
            self::COMPLETED => 'success',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::REJECTED, self::COMPLETED], true);
    }
}
