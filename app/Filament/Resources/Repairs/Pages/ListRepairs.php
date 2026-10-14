<?php

namespace App\Filament\Resources\Repairs\Pages;

use App\Enums\RepairStatus;
use App\Filament\Resources\Repairs\RepairResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRepairs extends ListRecords
{
    protected static string $resource = RepairResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Tiket'),
            'pending' => Tab::make('Menunggu Perbaikan')
                ->badge(fn (): int => (clone $this->getFilteredTableQuery())->where('status', RepairStatus::PENDING->value)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RepairStatus::PENDING->value)),
            'in_repair' => Tab::make('Sedang Diperbaiki')
                ->badge(fn (): int => (clone $this->getFilteredTableQuery())->where('status', RepairStatus::IN_REPAIR->value)->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RepairStatus::IN_REPAIR->value)),
            'ready_for_qc' => Tab::make('Siap Dicek Ulang')
                ->badge(fn (): int => (clone $this->getFilteredTableQuery())->where('status', RepairStatus::COMPLETED->value)->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RepairStatus::COMPLETED->value)),
            'verified' => Tab::make('Lolos QC Ulang')
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RepairStatus::VERIFIED->value)),
        ];
    }
}
