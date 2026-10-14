<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status alur bahan pesanan di gudang & cutting (Level 1).
 *
 * Transisi status digerakkan oleh event dan tindakan nyata:
 * - Default: KOSONG
 * - Klik PO -> ITEM_REQUEST
 * - Klik Konfirmasi Klien -> CLIENT_CONFIRMATION
 * - Keduanya aktif -> PO_AND_CLIENT
 * - Ada kiriman dalam perjalanan -> DELIVERY_IN_TRANSIT
 * - Stok input terpenuhi >= kebutuhan order -> STANDBY (Otomatis)
 * - Gudang mulai cutting & persiapan -> PREPARING_DELIVERY
 * - Gudang kirim ke lantai jahit -> DISPATCHED
 * - Kepala Produksi ACC penerimaan -> RECEIVED_BY_PRODUCTION
 */
enum OrderMaterialStatus: string implements HasColor, HasLabel
{
    case EMPTY = 'kosong';
    case ITEM_REQUEST = 'pengajuan_barang';
    case CLIENT_CONFIRMATION = 'konfirmasi_klien';
    case PO_AND_CLIENT = 'dalam_po_dan_konfirmasi_klien';
    case DELIVERY_IN_TRANSIT = 'pengiriman_barang';
    case STANDBY = 'standby';
    case PREPARING_DELIVERY = 'dalam_persiapan_pengiriman';
    case DISPATCHED = 'sedang_dikirim';
    case RECEIVED_BY_PRODUCTION = 'diterima_bagian_produksi';

    public function getLabel(): string
    {
        return match ($this) {
            self::EMPTY => 'Kosong (Belum Ada Bahan)',
            self::ITEM_REQUEST => 'Pengajuan Barang (PO)',
            self::CLIENT_CONFIRMATION => 'Konfirmasi Klien',
            self::PO_AND_CLIENT => 'Dalam PO & Konfirmasi Klien',
            self::DELIVERY_IN_TRANSIT => 'Pengiriman Barang',
            self::STANDBY => 'Standby (Bahan Lengkap)',
            self::PREPARING_DELIVERY => 'Dalam Persiapan Pengiriman (Cutting)',
            self::DISPATCHED => 'Sedang Dikirim',
            self::RECEIVED_BY_PRODUCTION => 'Diterima Bagian Produksi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EMPTY => 'gray',
            self::ITEM_REQUEST => 'purple',
            self::CLIENT_CONFIRMATION => 'info',
            self::PO_AND_CLIENT => 'amber',
            self::DELIVERY_IN_TRANSIT => 'warning',
            self::STANDBY => 'success',
            self::PREPARING_DELIVERY => 'info',
            self::DISPATCHED => 'primary',
            self::RECEIVED_BY_PRODUCTION => 'success',
        };
    }
}
