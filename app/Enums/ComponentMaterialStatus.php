<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status kondisi bahan, komponen, atau aksesoris per item (Level 2).
 */
enum ComponentMaterialStatus: string implements HasColor, HasLabel
{
    case EMPTY = 'kosong';
    case ITEM_REQUEST = 'pengajuan_barang';
    case WAITING_CLIENT = 'konfirmasi_klien';
    case DELIVERY_IN_TRANSIT = 'pengiriman_barang';
    case STANDBY = 'standby';
    case IN_CUTTING = 'dalam_cutting';
    case DISPATCHED = 'sedang_dikirim';
    case RECEIVED = 'diterima_produksi';

    public function getLabel(): string
    {
        return match ($this) {
            self::EMPTY => 'Kosong',
            self::ITEM_REQUEST => 'Dalam Pengajuan PO',
            self::WAITING_CLIENT => 'Konfirmasi Klien',
            self::DELIVERY_IN_TRANSIT => 'Dalam Pengiriman',
            self::STANDBY => 'Standby di Gudang',
            self::IN_CUTTING => 'Dalam Proses Cutting',
            self::DISPATCHED => 'Sedang Dikirim',
            self::RECEIVED => 'Diterima Produksi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EMPTY => 'gray',
            self::ITEM_REQUEST => 'purple',
            self::WAITING_CLIENT => 'info',
            self::DELIVERY_IN_TRANSIT => 'warning',
            self::STANDBY => 'success',
            self::IN_CUTTING => 'info',
            self::DISPATCHED => 'primary',
            self::RECEIVED => 'success',
        };
    }
}
