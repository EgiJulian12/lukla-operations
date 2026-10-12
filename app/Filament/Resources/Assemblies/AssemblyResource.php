<?php

namespace App\Filament\Resources\Assemblies;

use App\Filament\Resources\Assemblies\Pages\CreateAssembly;
use App\Filament\Resources\Assemblies\Pages\ListAssemblies;
use App\Filament\Resources\Assemblies\Schemas\AssemblyForm;
use App\Filament\Resources\Assemblies\Tables\AssembliesTable;
use App\Models\Assembly;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Perakitan komponen menjadi produk utuh (FR-013).
 *
 * Perakitan menyatukan output terverifikasi dari beberapa penjahit, lalu
 * memindahkan produk ke keadaan siap QC.
 */
class AssemblyResource extends Resource
{
    protected static ?string $model = Assembly::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Produksi';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'Perakitan';

    protected static ?string $pluralModelLabel = 'Perakitan';

    public static function form(Schema $schema): Schema
    {
        return AssemblyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssembliesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssemblies::route('/'),
            'create' => CreateAssembly::route('/create'),
        ];
    }
}
