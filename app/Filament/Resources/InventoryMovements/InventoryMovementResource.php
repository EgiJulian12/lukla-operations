<?php

namespace App\Filament\Resources\InventoryMovements;

use App\Filament\Concerns\RestrictsNavigationToFullAction;
use App\Filament\Resources\InventoryMovements\Pages\CreateInventoryMovement;
use App\Filament\Resources\InventoryMovements\Pages\ListInventoryMovements;
use App\Filament\Resources\InventoryMovements\Schemas\InventoryMovementForm;
use App\Filament\Resources\InventoryMovements\Tables\InventoryMovementsTable;
use App\Models\InventoryMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Pergerakan stok (FR-006, FR-007).
 *
 * Sengaja TIDAK punya halaman ubah: pergerakan adalah catatan peristiwa yang
 * tidak boleh disunting (InventoryMovementPolicy::update() selalu menolak).
 * Koreksi dilakukan dengan mencatat pergerakan penyesuaian baru.
 *
 * Pembuatan pergerakan dialihkan ke InventoryService (lihat
 * CreateInventoryMovement) — di situlah kecocokan klien/pesanan diperiksa dan
 * peringatan eksplisit ditampilkan sebelum transaksi dicoba (NFR-002).
 */
class InventoryMovementResource extends Resource
{
    use RestrictsNavigationToFullAction;

    protected static ?string $model = InventoryMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Gudang & Cutting';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Pergerakan Stok';

    protected static ?string $pluralModelLabel = 'Pergerakan Stok';

    public static function form(Schema $schema): Schema
    {
        return InventoryMovementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryMovementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryMovements::route('/'),
            'create' => CreateInventoryMovement::route('/create'),
        ];
    }
}
