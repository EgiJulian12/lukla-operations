<?php

namespace App\Filament\Resources\ApprovedSamples\Pages;

use App\Enums\SampleStatus;
use App\Enums\WorkScheme;
use App\Filament\Resources\ApprovedSamples\ApprovedSampleResource;
use App\Models\Sample;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Halaman daftar sample yang sudah disetujui (Approved) dengan filter tab skema maklon.
 */
class ListApprovedSamples extends ListRecords
{
    protected static string $resource = ApprovedSampleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua')
                ->badge(Sample::query()->where('status', SampleStatus::APPROVED->value)->count()),
            'cmt' => Tab::make('CMT')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('orders', fn (Builder $q) => $q->where('work_scheme', WorkScheme::CMT->value)))
                ->badge(Sample::query()->where('status', SampleStatus::APPROVED->value)->whereHas('orders', fn (Builder $q) => $q->where('work_scheme', WorkScheme::CMT->value))->count()),
            'semi_cmt' => Tab::make('Semi-CMT')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('orders', fn (Builder $q) => $q->where('work_scheme', WorkScheme::SEMI_CMT->value)))
                ->badge(Sample::query()->where('status', SampleStatus::APPROVED->value)->whereHas('orders', fn (Builder $q) => $q->where('work_scheme', WorkScheme::SEMI_CMT->value))->count()),
            'full_package' => Tab::make('CMT + Bahan')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('orders', fn (Builder $q) => $q->where('work_scheme', WorkScheme::FULL_PACKAGE->value)))
                ->badge(Sample::query()->where('status', SampleStatus::APPROVED->value)->whereHas('orders', fn (Builder $q) => $q->where('work_scheme', WorkScheme::FULL_PACKAGE->value))->count()),
        ];
    }
}
