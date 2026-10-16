<?php

namespace App\Filament\Resources\InventoryItems;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictsNavigationToFullAction;
use App\Filament\Resources\InventoryItems\Pages\CreateInventoryItem;
use App\Filament\Resources\InventoryItems\Pages\EditInventoryItem;
use App\Filament\Resources\InventoryItems\Pages\ListInventoryItems;
use App\Filament\Resources\InventoryItems\Schemas\InventoryItemForm;
use App\Filament\Resources\InventoryItems\Tables\InventoryItemsTable;
use App\Models\InventoryItem;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Stok gudang multi-klien — SRS view #3 "Gudang Multi-Klien & Stok" (FR-005).
 *
 * Isolasi klien berlaku tanpa kode tambahan di sini: global scope
 * BelongsToClient memotong daftar ke client_id akun yang login, dan
 * InventoryItemPolicy menolak tindakan tulis bagi peran selain Gudang.
 *
 * Pembuatan item dialihkan ke InventoryService (lihat CreateInventoryItem)
 * supaya jumlah stok selalu berasal dari pergerakan, bukan input formulir.
 */
class InventoryItemResource extends Resource
{
    use RestrictsNavigationToFullAction;

    protected static ?string $model = InventoryItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Gudang & Cutting';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Item Stok';

    protected static ?string $pluralModelLabel = 'Stok Gudang';

    protected static ?string $recordTitleAttribute = 'batch';

    public static function form(Schema $schema): Schema
    {
        return InventoryItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryItems::route('/'),
            'create' => CreateInventoryItem::route('/create'),
            'edit' => EditInventoryItem::route('/{record}/edit'),
        ];
    }

    /**
     * Finance tidak menyentuh stok fisik; itu wewenang Gudang (canManageInventory).
     * Tim Sample juga tidak menyentuh stok gudang.
     */
    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return ! $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role === UserRole::FINANCE_PAYROLL
                || $role === UserRole::SAMPLE_TEAM,
        );
    }
}
