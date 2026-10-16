<?php

namespace App\Filament\Portal\Resources\ApprovedSamples;

use App\Enums\SampleStatus;
use App\Filament\Portal\Resources\ApprovedSamples\Pages\ListPortalApprovedSamples;
use App\Filament\Portal\Resources\ApprovedSamples\Pages\ViewPortalApprovedSample;
use App\Filament\Portal\Resources\ApprovedSamples\Tables\PortalApprovedSamplesTable;
use App\Filament\Resources\Samples\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\Samples\Schemas\SampleForm;
use App\Models\Sample;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menu "Sample Disetujui" pada portal klien maklon (FR-003, FR-025).
 *
 * Menampilkan daftar sample milik klien yang sudah resmi disetujui (Approved).
 */
class PortalApprovedSampleResource extends Resource
{
    protected static ?string $model = Sample::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Sample Disetujui';

    protected static ?string $modelLabel = 'Sample Disetujui';

    protected static ?string $pluralModelLabel = 'Sample Disetujui';

    protected static ?int $navigationSort = 13;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', SampleStatus::APPROVED->value);
    }

    public static function form(Schema $schema): Schema
    {
        return SampleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PortalApprovedSamplesTable::configure($table);
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
            'index' => ListPortalApprovedSamples::route('/'),
            'view' => ViewPortalApprovedSample::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
