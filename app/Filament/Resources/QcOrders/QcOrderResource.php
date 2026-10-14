<?php

namespace App\Filament\Resources\QcOrders;

use App\Enums\UserRole;
use App\Filament\Resources\QcOrders\Pages\ListQcOrders;
use App\Filament\Resources\QcOrders\Tables\QcOrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Pintu masuk utama QC: Antrean Pesanan (Order-Centric QC Inspection Hub).
 *
 * Menampilkan pesanan yang sedang dalam proses produksi, status kesiapan QC,
 * rangkuman berapa yang sudah lolos, diperbaiki, dan ditolak.
 */
class QcOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Quality Control';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Antrean Pesanan';

    protected static ?string $pluralModelLabel = 'Antrean Pesanan (QC)';

    protected static ?string $slug = 'qc-orders';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canInspectQuality()
            || $role->canManageProduction()
            || $role->canConfigureSystem()
        );
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['client', 'assemblies.qcRecords', 'qcRecords'])
            ->productionStage();
    }

    public static function table(Table $table): Table
    {
        return QcOrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQcOrders::route('/'),
        ];
    }
}
