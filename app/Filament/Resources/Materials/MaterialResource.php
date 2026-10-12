<?php

namespace App\Filament\Resources\Materials;

use App\Filament\Resources\Materials\Pages\CreateMaterial;
use App\Filament\Resources\Materials\Pages\EditMaterial;
use App\Filament\Resources\Materials\Pages\ListMaterials;
use App\Filament\Resources\Materials\Schemas\MaterialForm;
use App\Filament\Resources\Materials\Tables\MaterialsTable;
use App\Models\Material;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Data induk bahan (matriks RBAC, Gudang).
 *
 * Bahan tanpa klien bersifat generik dan dipakai lintas order; bahan yang
 * terikat klien adalah titipan yang hanya boleh dikeluarkan untuk order klien
 * tersebut.
 */
class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'Bahan';

    protected static ?string $pluralModelLabel = 'Bahan';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MaterialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaterialsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaterials::route('/'),
            'create' => CreateMaterial::route('/create'),
            'edit' => EditMaterial::route('/{record}/edit'),
        ];
    }
}
