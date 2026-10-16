<?php

namespace App\Filament\Portal\Resources\Samples;

use App\Filament\Portal\Resources\Samples\Pages\ListPortalSamples;
use App\Filament\Portal\Resources\Samples\Pages\ViewPortalSample;
use App\Filament\Portal\Resources\Samples\RelationManagers\PortalRevisionsRelationManager;
use App\Filament\Portal\Resources\Samples\Schemas\PortalSampleInfolist;
use App\Filament\Portal\Resources\Samples\Tables\PortalSamplesTable;
use App\Models\Sample;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Manajemen sample dan verifikasi hasil sample untuk Klien Maklon (FR-003, FR-025).
 *
 * Klien dapat melihat detail spesifikasi, mengunduh acuan desain awal,
 * serta melihat dokumentasi fisik foto hasil sample dan memberikan keputusan (ACC / Tolak).
 */
class PortalSampleResource extends Resource
{
    protected static ?string $model = Sample::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $navigationLabel = 'Sample Saya';

    protected static ?string $modelLabel = 'Sample';

    protected static ?string $pluralModelLabel = 'Sample Saya';

    protected static ?int $navigationSort = 12;

    public static function infolist(Schema $schema): Schema
    {
        return PortalSampleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PortalSamplesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PortalRevisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortalSamples::route('/'),
            'view' => ViewPortalSample::route('/{record}'),
        ];
    }
}
