<?php

namespace App\Filament\Resources\WasteRecords;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictsNavigationToFullAction;
use App\Filament\Resources\WasteRecords\Pages\CreateWasteRecord;
use App\Filament\Resources\WasteRecords\Pages\EditWasteRecord;
use App\Filament\Resources\WasteRecords\Pages\ListWasteRecords;
use App\Filament\Resources\WasteRecords\Schemas\WasteRecordForm;
use App\Filament\Resources\WasteRecords\Tables\WasteRecordsTable;
use App\Models\User;
use App\Models\WasteRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Sisa potongan / scrap per klien dan pesanan (FR-008).
 *
 * Dicatat Gudang saat cutting berjalan, dan dipakai untuk pelaporan efisiensi
 * bahan per klien.
 */
class WasteRecordResource extends Resource
{
    use RestrictsNavigationToFullAction;

    protected static ?string $model = WasteRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Gudang & Cutting';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Sisa Potongan';

    protected static ?string $pluralModelLabel = 'Sisa Potongan';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return WasteRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WasteRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWasteRecords::route('/'),
            'create' => CreateWasteRecord::route('/create'),
            'edit' => EditWasteRecord::route('/{record}/edit'),
        ];
    }

    /**
     * Finance tidak mencatat sisa potongan; itu domain Gudang.
     * Tim Sample tidak mencatat sisa potongan.
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
