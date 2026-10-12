<?php

namespace App\Filament\Resources\ProductionLines;

use App\Filament\Resources\ProductionLines\Pages\CreateProductionLine;
use App\Filament\Resources\ProductionLines\Pages\EditProductionLine;
use App\Filament\Resources\ProductionLines\Pages\ListProductionLines;
use App\Filament\Resources\ProductionLines\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\ProductionLines\Schemas\ProductionLineForm;
use App\Filament\Resources\ProductionLines\Tables\ProductionLinesTable;
use App\Models\ProductionLine;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Line produksi paralel (FR-009).
 *
 * Kapasitas line bersifat cair: jumlah mesin pada kolom kapasitas hanya
 * indikator, bukan batas jumlah penjahit yang boleh dialokasikan.
 */
class ProductionLineResource extends Resource
{
    protected static ?string $model = ProductionLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Produksi';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Line Produksi';

    protected static ?string $pluralModelLabel = 'Line Produksi';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProductionLineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionLinesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AssignmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionLines::route('/'),
            'create' => CreateProductionLine::route('/create'),
            'edit' => EditProductionLine::route('/{record}/edit'),
        ];
    }
}
