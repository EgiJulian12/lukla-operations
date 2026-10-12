<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Finance\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar invoice klien dengan aksi terbit dan tandai lunas (matriks RBAC SRS,
 * Finance).
 *
 * Setiap aksi hanya muncul pada keadaan yang tepat dan bagi pengguna yang
 * berwenang, sehingga urutan draf, terbit, lunas tidak dapat dilompati.
 */
class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('Nilai')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('issued_at')
                    ->label('Terbit')
                    ->date()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('due_at')
                    ->label('Jatuh Tempo')
                    ->date()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(InvoiceStatus::class),
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('issue')
                    ->label('Terbitkan')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record): bool => (bool) auth()->user()?->can('issue', $record))
                    ->action(fn (Invoice $record) => app(InvoiceService::class)->issue($record))
                    ->successNotificationTitle('Invoice diterbitkan'),
                Action::make('pay')
                    ->label('Tandai Lunas')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record): bool => (bool) auth()->user()?->can('pay', $record))
                    ->action(fn (Invoice $record) => app(InvoiceService::class)->markPaid($record))
                    ->successNotificationTitle('Invoice ditandai lunas'),
                EditAction::make()
                    ->label('Ubah'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
