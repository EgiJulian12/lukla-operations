<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\OutputStatus;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Progres order per klien (SRS view #1, FR-019).
 *
 * Hanya order yang masih berjalan yang ditampilkan, supaya dashboard fokus
 * pada pekerjaan yang perlu ditindak lanjuti.
 */
class OrderProgressTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Progres Order Berjalan')
            ->query(
                Order::query()
                    ->with('client')
                    ->productionStage()
                    ->whereNotIn('status', [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value])
                    ->limit(10),
            )
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order'),
                TextColumn::make('client.name')
                    ->label('Klien'),
                TextColumn::make('quantity')
                    ->label('Target')
                    ->numeric(),
                TextColumn::make('verified_output')
                    ->label('Terverifikasi')
                    ->state(fn (Order $record): int => (int) $record->outputEntries()
                        ->where('status', OutputStatus::VERIFIED->value)
                        ->sum('quantity'))
                    ->numeric(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->defaultSort('due_date');
    }
}
