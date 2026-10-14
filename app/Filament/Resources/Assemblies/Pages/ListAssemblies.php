<?php

namespace App\Filament\Resources\Assemblies\Pages;

use App\Filament\Resources\Assemblies\AssemblyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAssemblies extends ListRecords
{
    protected static string $resource = AssemblyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Rakit Produk'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'pending_qc' => Tab::make('Menunggu QC')
                ->badge(fn (): int => (clone $this->getFilteredTableQuery())->pendingQc()->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->pendingQc()),
            'all' => Tab::make('Semua Perakitan'),
            'completed_qc' => Tab::make('Selesai QC')
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereRaw('quantity <= (SELECT COALESCE(SUM(quantity), 0) FROM qc_records WHERE qc_records.assembly_id = assemblies.id)')),
        ];
    }
}
