<?php

namespace App\Filament\Portal\Resources\InventoryItems;

use App\Filament\Portal\Resources\InventoryItems\Pages\ListPortalInventoryItems;
use App\Filament\Portal\Resources\InventoryItems\Tables\PortalInventoryItemsTable;
use App\Models\InventoryItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Saldo bahan titipan milik klien yang sedang login (FR-025).
 *
 * Hanya-baca, dan hanya baris dengan client_id klien tersebut yang terlihat.
 */
class PortalInventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Stok Titipan Saya';

    protected static ?string $modelLabel = 'Baris Stok';

    protected static ?string $pluralModelLabel = 'Stok Titipan Saya';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return PortalInventoryItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortalInventoryItems::route('/'),
        ];
    }
}
