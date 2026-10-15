<?php

namespace App\Filament\Resources\ApprovedSamples;

use App\Enums\OrderStatus;
use App\Enums\SampleStatus;
use App\Filament\Concerns\RestrictsNavigationToFullAction;
use App\Filament\Resources\ApprovedSamples\Pages\ListApprovedSamples;
use App\Filament\Resources\ApprovedSamples\Pages\ViewApprovedSample;
use App\Filament\Resources\ApprovedSamples\Schemas\ApprovedSampleInfolist;
use App\Filament\Resources\ApprovedSamples\Tables\ApprovedSamplesTable;
use App\Models\Sample;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Resource untuk menampilkan daftar dan detail sample yang sudah disetujui (Approved).
 *
 * Menu ini didedikasikan untuk Kepala Produksi dan Tim Sample guna memantau rincian
 * pengerjaan komponen, durasi time study (SMV), dan status tarif borongan per skema maklon.
 */
class ApprovedSampleResource extends Resource
{
    use RestrictsNavigationToFullAction;

    protected static ?string $model = Sample::class;

    protected static ?string $slug = 'approved-samples';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Produksi';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Sample Disetujui';

    protected static ?string $pluralModelLabel = 'Sample Disetujui';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getEloquentQuery(): Builder
    {
        $productionStatuses = [
            OrderStatus::READY_FOR_PRODUCTION->value,
            OrderStatus::IN_PRODUCTION->value,
            OrderStatus::IN_QC->value,
            OrderStatus::COMPLETED->value,
        ];

        return parent::getEloquentQuery()
            ->where('status', SampleStatus::APPROVED->value)
            ->whereDoesntHave('orders', fn (Builder $query) => $query->whereIn('status', $productionStatuses));
    }

    public static function infolist(Schema $schema): Schema
    {
        return ApprovedSampleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApprovedSamplesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovedSamples::route('/'),
            'view' => ViewApprovedSample::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
