<?php

namespace App\Filament\Resources\MaterialDispatches;

use App\Enums\UserRole;
use App\Filament\Resources\MaterialDispatches\Pages\CreateMaterialDispatch;
use App\Filament\Resources\MaterialDispatches\Pages\ListMaterialDispatches;
use App\Filament\Resources\MaterialDispatches\Schemas\MaterialDispatchForm;
use App\Filament\Resources\MaterialDispatches\Tables\MaterialDispatchesTable;
use App\Models\MaterialDispatch;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Pelacakan permintaan dan pengantaran bahan dari gudang ke bagian produksi / cutting (FR-012, Track D).
 */
class MaterialDispatchResource extends Resource
{
    protected static ?string $model = MaterialDispatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|UnitEnum|null $navigationGroup = 'Gudang & Cutting';

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'Permintaan & Pengiriman Bahan';

    protected static ?string $pluralModelLabel = 'Pengantaran Bahan ke Produksi';

    protected static ?string $recordTitleAttribute = 'dispatch_number';

    public static function form(Schema $schema): Schema
    {
        return MaterialDispatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaterialDispatchesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaterialDispatches::route('/'),
            'create' => CreateMaterialDispatch::route('/create'),
        ];
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return ! $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role === UserRole::FINANCE_PAYROLL
                || $role === UserRole::SAMPLE_TEAM
                || $role === UserRole::QC_INSPECTOR,
        );
    }
}
