<?php

namespace App\Filament\Resources\RejectedItems;

use App\Filament\Resources\RejectedItems\Pages\ListRejectedItems;
use App\Filament\Resources\RejectedItems\Tables\RejectedItemsTable;
use App\Models\QcRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Menu Barang Ditolak / Afkir (SCRAP) di bawah Quality Control.
 */
class RejectedItemResource extends Resource
{
    protected static ?string $model = QcRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedXCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Quality Control';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Barang Ditolak';

    protected static ?string $pluralModelLabel = 'Barang Ditolak (Scrap)';

    protected static ?string $slug = 'rejected-items';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return RejectedItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRejectedItems::route('/'),
        ];
    }
}
