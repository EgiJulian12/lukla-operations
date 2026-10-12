<?php

namespace App\Filament\Portal\Resources\QcRecords;

use App\Filament\Portal\Resources\QcRecords\Pages\ListPortalQcRecords;
use App\Filament\Portal\Resources\QcRecords\Tables\PortalQcRecordsTable;
use App\Models\QcRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Hasil inspeksi QC atas produk klien yang sedang login (FR-025).
 *
 * Hanya-baca. Yang ditampilkan adalah keputusan QC yang sudah final — lolos,
 * repair, atau scrap — bukan angka internal lantai produksi.
 */
class PortalQcRecordResource extends Resource
{
    protected static ?string $model = QcRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Hasil QC Saya';

    protected static ?string $modelLabel = 'Hasil QC';

    protected static ?string $pluralModelLabel = 'Hasil QC Saya';

    protected static ?int $navigationSort = 15;

    public static function table(Table $table): Table
    {
        return PortalQcRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortalQcRecords::route('/'),
        ];
    }
}
