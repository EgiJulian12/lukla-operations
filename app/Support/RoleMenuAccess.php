<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Filament\Resources\ApprovedSamples\ApprovedSampleResource;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Assemblies\AssemblyResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Components\ComponentResource;
use App\Filament\Resources\DefectCategories\DefectCategoryResource;
use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Materials\MaterialResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\OutputEntries\OutputEntryResource;
use App\Filament\Resources\OverheadAllocations\OverheadAllocationResource;
use App\Filament\Resources\OverheadRecords\OverheadRecordResource;
use App\Filament\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Filament\Resources\ProductionLines\ProductionLineResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\QcRecords\QcRecordResource;
use App\Filament\Resources\RateCards\RateCardResource;
use App\Filament\Resources\Samples\SampleResource;
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
                ApprovedSampleResource::class,
                ArticleResource::class,
                ComponentResource::class,
                OrderResource::class,
            ],

            UserRole::WAREHOUSE->value => [
                InventoryItemResource::class,
                InventoryMovementResource::class,
                WasteRecordResource::class,
                MaterialResource::class,
            ],

            // Kepala Produksi memeriksa output lewat daftar berbasis order
            // (menu "Output 30 Menit" miliknya sendiri), bukan lewat log datar
            // OutputEntryResource — menu itu pintu masuk penjahit, dan akses
            // URL-nya tetap terbuka bagi mandor lewat policy.
            UserRole::PRODUCTION_LEAD->value => [
                ProductionLineResource::class,
                AssemblyResource::class,
                RateCardResource::class,
                ApprovedSampleResource::class,
            ],

            UserRole::TAILOR_OPERATOR->value => [
                OutputEntryResource::class,
            ],

            UserRole::QC_INSPECTOR->value => [
                QcRecordResource::class,
                DefectCategoryResource::class,
            ],

            UserRole::FINANCE_PAYROLL->value => [
                PayrollPeriodResource::class,
                RateCardResource::class,
                PurchaseOrderResource::class,
                OverheadRecordResource::class,
                OverheadAllocationResource::class,
                InvoiceResource::class,
                ClientResource::class,
            ],

            UserRole::OWNER_EXECUTIVE->value => [
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
