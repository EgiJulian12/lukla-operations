<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Order'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Order'),
            'sample_queue' => Tab::make('Antrean Sample (Order Di-ACC)')
                ->badge(fn (): int => OrderResource::getEloquentQuery()->where('status', OrderStatus::QUOTE_APPROVED->value)->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', OrderStatus::QUOTE_APPROVED->value)),
        ];
    }
}
