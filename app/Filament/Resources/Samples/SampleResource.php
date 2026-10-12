<?php

namespace App\Filament\Resources\Samples;

use App\Filament\Resources\Samples\Pages\CreateSample;
use App\Filament\Resources\Samples\Pages\EditSample;
use App\Filament\Resources\Samples\Pages\ListSamples;
use App\Filament\Resources\Samples\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\Samples\Schemas\SampleForm;
use App\Filament\Resources\Samples\Tables\SamplesTable;
use App\Models\Sample;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Pelacak sample dan revisinya (SRS view #2, FR-001 sampai FR-004).
 *
 * Revisi dikelola lewat RelationManager karena versioning adalah operasi
 * tersendiri, sedangkan konversi ke order massal disediakan sebagai aksi pada
 * baris sample yang sudah punya revisi disetujui.
 */
class SampleResource extends Resource
{
    protected static ?string $model = Sample::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Sample & Time Study';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Sample';

    protected static ?string $pluralModelLabel = 'Sample';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return SampleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SamplesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RevisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSamples::route('/'),
            'create' => CreateSample::route('/create'),
            'edit' => EditSample::route('/{record}/edit'),
        ];
    }
}
