<?php

namespace App\Filament\Resources\OrderMaterialFlows;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\OrderMaterialFlows\Pages\ListOrderMaterialFlows;
use App\Filament\Resources\OrderMaterialFlows\Tables\OrderMaterialFlowsTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Menu Khusus Alur Pergerakan Bahan Berdasarkan Pesanan (Order-Centric Warehouse & Cutting Hub).
 */
class OrderMaterialFlowResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Gudang & Cutting';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Alur Bahan Pesanan';

    protected static ?string $pluralModelLabel = 'Alur Bahan Pesanan';

    protected static ?string $slug = 'order-material-flows';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageInventory()
            || $role->canManageProduction()
            || $role->canConfigureSystem()
            || $role->canManageOrders()
        );
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['client', 'materialRequirements', 'sample'])
            ->where('status', '!=', OrderStatus::CANCELLED->value);
    }

    public static function table(Table $table): Table
    {
        return OrderMaterialFlowsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrderMaterialFlows::route('/'),
        ];
    }
}
