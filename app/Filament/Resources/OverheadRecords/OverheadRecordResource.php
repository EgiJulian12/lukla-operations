<?php

namespace App\Filament\Resources\OverheadRecords;

use App\Filament\Concerns\RestrictsNavigationToFullAction;
use App\Filament\Resources\OverheadRecords\Pages\CreateOverheadRecord;
use App\Filament\Resources\OverheadRecords\Pages\EditOverheadRecord;
use App\Filament\Resources\OverheadRecords\Pages\ListOverheadRecords;
use App\Filament\Resources\OverheadRecords\Schemas\OverheadRecordForm;
use App\Filament\Resources\OverheadRecords\Tables\OverheadRecordsTable;
use App\Models\OverheadRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Biaya overhead operasional bulanan (FR-018).
 *
 * Catatan listrik, air, internet, dan biaya pabrik lain yang menjadi dasar
 * perhitungan overhead per unit.
 */
class OverheadRecordResource extends Resource
{
    use RestrictsNavigationToFullAction;

    protected static ?string $model = OverheadRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Payroll & Biaya';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Biaya Overhead';

    protected static ?string $pluralModelLabel = 'Biaya Overhead';

    public static function form(Schema $schema): Schema
    {
        return OverheadRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OverheadRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOverheadRecords::route('/'),
            'create' => CreateOverheadRecord::route('/create'),
            'edit' => EditOverheadRecord::route('/{record}/edit'),
        ];
    }
}
