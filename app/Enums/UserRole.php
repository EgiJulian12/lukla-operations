<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Delapan peran pengguna (RBAC matrix).
 *
 * Nama case memakai bahasa Inggris untuk kode; label dan nama role di database
 * memakai istilah operasional Indonesia yang dipakai di lantai produksi.
 *
 * @see docs/lukla-operations-engine.md — Actor scope and permissions
 */
enum UserRole: string implements HasLabel
{
    case OWNER_EXECUTIVE = 'owner_eksekutif';
    case SAMPLE_TEAM = 'tim_sample';
    case WAREHOUSE = 'gudang';
    case PRODUCTION_LEAD = 'kepala_produksi';
    case TAILOR_OPERATOR = 'penjahit';
    case QC_INSPECTOR = 'qc_inspector';
    case FINANCE_PAYROLL = 'finance_payroll';
    case CLIENT_MAKLON = 'klien_maklon';

    public function getLabel(): string
    {
        return match ($this) {
            self::OWNER_EXECUTIVE => 'Owner / Eksekutif Internal',
            self::SAMPLE_TEAM => 'Tim Sample / Adis Sample',
            self::WAREHOUSE => 'Gudang / Warehouse Stok',
            self::PRODUCTION_LEAD => 'Kepala Produksi / Mandor Line',
            self::TAILOR_OPERATOR => 'Penjahit / Tailor Operator',
            self::QC_INSPECTOR => 'QC Inspector / Quality',
            self::FINANCE_PAYROLL => 'Finance & Payroll',
            self::CLIENT_MAKLON => 'Klien Maklon / B2B Portal',
        };
    }

    /**
     * Peran internal pabrik — memakai panel /admin dengan akses lintas klien.
     */
    public static function internal(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => $role !== self::CLIENT_MAKLON,
        ));
    }

    /**
     * Peran klien maklon — memakai panel /portal, terbatas pada client_id sendiri.
     * Ini satu-satunya peran yang di-scope ke tenant (NFR-004, FR-025).
     */
    public function isClientFacing(): bool
    {
        return $this === self::CLIENT_MAKLON;
    }

    /**
     * Persetujuan versi sample (FR-003, FR-026). Matriks RBAC SRS menempatkan
     * "Setujui versi sample" pada Tim Sample; Owner tetap berwenang menyetujui.
     */
    public function canApproveSamples(): bool
    {
        return in_array($this, [self::OWNER_EXECUTIVE, self::SAMPLE_TEAM], true);
    }

    /**
     * Boleh menerima bahan, menandai kepemilikan, mengeluarkan ke cutting, dan
     * melakukan stock opname (matriks RBAC — Gudang).
     *
     * Peran lain hanya boleh MELIHAT stok: Finance dilarang mengubah stok fisik,
     * QC dan Penjahit tidak menyentuh gudang, Kepala Produksi memantau progres.
     */
    public function canManageInventory(): bool
    {
        return $this === self::WAREHOUSE;
    }

    /**
     * Boleh mencatat sisa potongan / scrap beserta kategorinya (FR-008).
     */
    public function canRecordWaste(): bool
    {
        return $this === self::WAREHOUSE;
    }

    /**
     * Boleh membuat sample, revisi berurutan, pemakaian bahan, dan time study
     * komponen, lalu mengajukan revisi untuk disetujui (FR-001, FR-002, FR-005).
     */
    public function canManageSamples(): bool
    {
        return $this === self::SAMPLE_TEAM;
    }

    /**
     * Boleh membuat order produksi massal dari sample yang sudah disetujui,
     * lalu memelihara order tersebut selama belum terkunci (FR-004).
     */
    public function canManageOrders(): bool
    {
        return in_array($this, [self::OWNER_EXECUTIVE, self::SAMPLE_TEAM], true);
    }

    /**
     * Konfigurasi sistem dan kebijakan lintas modul, mis. tarif bonus QC dan
     * persentase uang makan default (matriks RBAC, Owner).
     */
    public function canConfigureSystem(): bool
    {
        return $this === self::OWNER_EXECUTIVE;
    }

    /**
     * Boleh mengatur line, mengalokasikan penjahit, dan memverifikasi output
     * 30 menit (FR-009, FR-012).
     */
    public function canManageProduction(): bool
    {
        return $this === self::PRODUCTION_LEAD;
    }

    /**
     * Verifikasi output adalah wewenang mandor, terpisah dari yang mencatat
     * output, sehingga penjahit tidak bisa memverifikasi pekerjaannya sendiri
     * (FR-012, separasi tugas).
     */
    public function canVerifyOutput(): bool
    {
        return $this === self::PRODUCTION_LEAD;
    }

    /**
     * Boleh melaporkan output 30 menit untuk tugasnya sendiri (FR-011).
     */
    public function canSubmitOutput(): bool
    {
        return $this === self::TAILOR_OPERATOR;
    }

    /**
     * Boleh memeriksa barang jadi dan menetapkan lolos, repair, atau scrap
     * beserta kategori cacatnya (FR-014).
     */
    public function canInspectQuality(): bool
    {
        return $this === self::QC_INSPECTOR;
    }

    /**
     * Boleh menghitung dan menyetujui payroll, serta memelihara input biaya
     * seperti uang makan dan overhead (FR-015, FR-016, FR-018). Finance tidak
     * boleh menyentuh stok fisik; itu wewenang Gudang.
     */
    public function canManagePayroll(): bool
    {
        return $this === self::FINANCE_PAYROLL;
    }

    /**
     * Boleh membuat dan menyetujui PO bahan (matriks RBAC SRS, Finance).
     */
    public function canManagePurchaseOrders(): bool
    {
        return $this === self::FINANCE_PAYROLL;
    }

    /**
     * Boleh menerbitkan invoice klien (matriks RBAC SRS, Finance).
     */
    public function canManageInvoices(): bool
    {
        return $this === self::FINANCE_PAYROLL;
    }

    /**
     * Tarif per komponen ditentukan Kepala Produksi di lantai produksi, di
     * samping Finance dan Owner yang memegang kebijakan tarif (matriks RBAC SRS).
     */
    public function canManageRateCards(): bool
    {
        return in_array($this, [self::OWNER_EXECUTIVE, self::FINANCE_PAYROLL, self::PRODUCTION_LEAD], true);
    }

    /**
     * Boleh membuat dan menyunting data induk (klien, artikel, bahan, kategori
     * cacat). Dipisah dari transaksi operasional agar CRUD referensi tidak
     * tertukar dengan wewenang produksi.
     */
    public function canManageClients(): bool
    {
        return in_array($this, [self::OWNER_EXECUTIVE, self::FINANCE_PAYROLL], true);
    }

    public function canManageArticles(): bool
    {
        return $this === self::SAMPLE_TEAM;
    }

    public function canManageMaterials(): bool
    {
        return $this === self::WAREHOUSE;
    }

    public function canManageDefectCategories(): bool
    {
        return $this === self::QC_INSPECTOR;
    }
}
