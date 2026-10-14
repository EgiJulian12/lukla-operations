<?php

namespace App\Filament\Resources\QcRecords\Pages;

use App\Enums\QcResult;
use App\Filament\Resources\QcRecords\QcRecordResource;
use App\Filament\Resources\QcRecords\Tables\QcRecordsTable;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListQcRecords extends ListRecords
{
    protected static string $resource = QcRecordResource::class;

    public bool $showAll = false;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function toggleShowAll(): void
    {
        $this->showAll = ! $this->showAll;
    }

    public function resetTableFiltersForm(): void
    {
        $this->showAll = false;
        parent::resetTableFiltersForm();
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Riwayat')
                ->badge(fn (): ?int => QcRecordsTable::hasActiveFilter($this)
                    ? (clone $this->getFilteredTableQuery())->count()
                    : null
                ),
            'pass' => Tab::make('Lolos (Pass)')
                ->badge(fn (): ?int => QcRecordsTable::hasActiveFilter($this)
                    ? (clone $this->getFilteredTableQuery())->where('result', QcResult::PASS->value)->count()
                    : null
                )
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('result', QcResult::PASS->value)),
            'repair' => Tab::make('Perlu Repair')
                ->badge(fn (): ?int => QcRecordsTable::hasActiveFilter($this)
                    ? (clone $this->getFilteredTableQuery())->where('result', QcResult::REPAIR->value)->count()
                    : null
                )
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('result', QcResult::REPAIR->value)),
            'scrap' => Tab::make('Reject / Scrap')
                ->badge(fn (): ?int => QcRecordsTable::hasActiveFilter($this)
                    ? (clone $this->getFilteredTableQuery())->where('result', QcResult::SCRAP->value)->count()
                    : null
                )
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('result', QcResult::SCRAP->value)),
        ];
    }
}
