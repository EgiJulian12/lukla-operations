<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Filament\Resources\ApprovedSamples\ApprovedSampleResource;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Components\ComponentResource;
use App\Filament\Resources\DefectCategories\DefectCategoryResource;
use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Materials\MaterialResource;
use App\Filament\Resources\MyPurchaseOrders\MyPurchaseOrderResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\OutputEntries\OutputEntryResource;
use App\Filament\Resources\OverheadAllocations\OverheadAllocationResource;
use App\Filament\Resources\OverheadRecords\OverheadRecordResource;
use App\Filament\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\QcBonuses\QcBonusResource;
use App\Filament\Resources\QcRecords\QcRecordResource;
use App\Filament\Resources\RateCards\RateCardResource;
use App\Filament\Resources\Repairs\RepairResource;
use App\Filament\Resources\Samples\SampleResource;
use App\Filament\Resources\TailorWages\TailorWageResource;
use App\Filament\Resources\WasteRecords\WasteRecordResource;
use App\Models\User;

class RoleMenuAccess
{
    /**
     * Matriks resource per peran yang memiliki hak akses penuh (Full Action / Manage).
     * Menu yang hanya bersifat read-only bagi suatu peran akan disembunyikan dari sidebar /admin.
     *
     * @return array<string, array<int, class-string>>
     */
    public static function fullActionMatrix(): array
    {
        return [
            UserRole::SAMPLE_TEAM->value => [
                SampleResource::class,
                ArticleResource::class,
                ComponentResource::class,
                OrderResource::class,
            ],

            UserRole::WAREHOUSE->value => [
                InventoryItemResource::class,
                InventoryMovementResource::class,
                WasteRecordResource::class,
                MaterialResource::class,
                MyPurchaseOrderResource::class,
            ],

            // Kepala Produksi memeriksa output lewat daftar berbasis order
            // (menu "Output 30 Menit" miliknya sendiri), bukan lewat log datar
            // OutputEntryResource — menu itu pintu masuk penjahit, dan akses
            // URL-nya tetap terbuka bagi mandor lewat policy.
            //
            // Menunya berhenti di produksi: Quality Control dijalankan QC
            // Inspector, dan tarif/biaya dipegang Finance bersama Owner. Rate
            // Card tidak lagi ikut di sini supaya grup "Payroll & Biaya" tidak
            // menempel di sidebar Kepala Produksi — hak aksesnya sendiri tetap
            // di RateCardPolicy, terpisah dari daftar menu ini.
            //
            // Line Produksi dan Perakitan juga tidak lagi berdiri sebagai menu
            // mandor: keduanya pengaturan lantai, sedangkan pekerjaan harian
            // Kepala Produksi ada di Antrean Produksi dan Output 30 Menit. Sama
            // seperti menu lain yang dilepas dari daftar ini, halamannya masih
            // dapat dibuka lewat URL selama canAccess()-nya mengizinkan.
            UserRole::PRODUCTION_LEAD->value => [
                ApprovedSampleResource::class,
            ],

            UserRole::TAILOR_OPERATOR->value => [
                OutputEntryResource::class,
                TailorWageResource::class,
            ],

            // Ketiga menu grup "Quality Control" didaftarkan di sini, bukan
            // hanya terminal inspeksinya: kalau salah satu lolos dari matriks,
            // grup itu menempel di sidebar peran lain yang kebetulan boleh
            // membukanya.
            UserRole::QC_INSPECTOR->value => [
                QcOrderResource::class,
                QcRecordResource::class,
                RepairResource::class,
                DefectCategoryResource::class,
                QcBonusResource::class,
            ],

            // Finance dan Owner boleh meninjau pengajuan gudang lewat menunya
            // sendiri ("Pengajuan PO Gudang") selain memelihara PO lewat
            // PurchaseOrderResource. Gudang ikut di daftar atas karena dialah
            // pengaju PO-nya — lihat PurchaseOrderPolicy::canView().
            UserRole::FINANCE_PAYROLL->value => [
                PayrollPeriodResource::class,
                RateCardResource::class,
                PurchaseOrderResource::class,
                OverheadRecordResource::class,
                OverheadAllocationResource::class,
                InvoiceResource::class,
                ClientResource::class,
                MyPurchaseOrderResource::class,
            ],

            UserRole::OWNER_EXECUTIVE->value => [
                UserResource::class,
                ClientResource::class,
                OrderResource::class,
                SampleResource::class,
                ApprovedSampleResource::class,
                RateCardResource::class,
                PayrollPeriodResource::class,
                InvoiceResource::class,
                PurchaseOrderResource::class,
                OverheadRecordResource::class,
                OverheadAllocationResource::class,
                QcOrderResource::class,
                RepairResource::class,
                MyPurchaseOrderResource::class,
            ],
        ];
    }

    /**
     * Memeriksa apakah pengguna berhak melihat resource tertentu pada sidebar panel /admin.
     */
    public static function canAccess(?User $user, string $resourceClass): bool
    {
        if ($user === null) {
            return false;
        }

        $roleNames = $user->getRoleNames();
        $matrix = static::fullActionMatrix();

        foreach ($roleNames as $roleName) {
            if (isset($matrix[$roleName]) && in_array($resourceClass, $matrix[$roleName], true)) {
                return true;
            }
        }

        return false;
    }
}
