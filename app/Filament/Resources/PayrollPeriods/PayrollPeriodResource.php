<?php

namespace App\Filament\Resources\PayrollPeriods;

use App\Filament\Resources\PayrollPeriods\Pages\CreatePayrollPeriod;
use App\Filament\Resources\PayrollPeriods\Pages\EditPayrollPeriod;
use App\Filament\Resources\PayrollPeriods\Pages\ListPayrollPeriods;
use App\Filament\Resources\PayrollPeriods\RelationManagers\PayrollEntriesRelationManager;
use App\Filament\Resources\PayrollPeriods\Schemas\PayrollPeriodForm;
use App\Filament\Resources\PayrollPeriods\Tables\PayrollPeriodsTable;
use App\Models\PayrollPeriod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Workspace payroll mingguan (SRS view #6, FR-015 sampai FR-017).
 *
 * Periode mengumpulkan penjahit lintas order dan lintas klien, sehingga model
 * ini tidak di-scope per klien. Perhitungan, persetujuan, dan penandaan bayar
 * dijalankan lewat PayrollService dari daftar periode.
 */
class PayrollPeriodResource extends Resource
{
    protected static ?string $model = PayrollPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Payroll & Biaya';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Periode Payroll';

    protected static ?string $pluralModelLabel = 'Periode Payroll';

    public static function form(Schema $schema): Schema
    {
        return PayrollPeriodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollPeriodsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PayrollEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollPeriods::route('/'),
            'create' => CreatePayrollPeriod::route('/create'),
            'edit' => EditPayrollPeriod::route('/{record}/edit'),
        ];
    }
}
