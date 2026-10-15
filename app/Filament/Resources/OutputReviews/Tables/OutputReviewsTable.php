<?php

namespace App\Filament\Resources\OutputReviews\Tables;

use App\Enums\OrderStatus;
use App\Enums\OutputStatus;
use App\Filament\Resources\OutputReviews\OutputReviewResource;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar order yang menunggu diperiksa outputnya oleh Kepala Produksi.
 *
 * Tiap baris menjawab satu pertanyaan: order ini masih ada yang menunggu
 * verifikasi atau tidak. Baris yang sudah bersih bisa langsung dibuka untuk
 * dirakit, sedangkan baris yang masih menunggu diverifikasi lebih dulu di
 * halaman detailnya.
 */
class OutputReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Nomor Order')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('article.name')
                    ->label('Artikel')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Target')
                    ->numeric()
                    ->placeholder('-')
                    ->formatStateUsing(fn (int $state): string => "{$state} pcs")
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('verification_progress')
                    ->label('Verifikasi')
                    ->badge()
                    ->state(fn (Order $record): string => self::verificationLabel($record))
                    ->color(fn (Order $record): string => self::verificationColor($record)),
                TextColumn::make('assemblies_count')
                    ->label('Perakitan')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn (int $state): string => $state > 0
                        ? "{$state} perakitan"
                        : 'Belum dirakit'),
                TextColumn::make('due_date')
                    ->label('Tenggat')
                    ->date('d/m/Y')
                    ->placeholder('Tanpa tenggat')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        OrderStatus::IN_PRODUCTION->value => OrderStatus::IN_PRODUCTION->getLabel(),
                        OrderStatus::IN_QC->value => OrderStatus::IN_QC->getLabel(),
                    ]),
                TernaryFilter::make('pending_verification')
                    ->label('Menunggu Verifikasi')
                    ->placeholder('Semua order')
                    ->trueLabel('Masih menunggu verifikasi')
                    ->falseLabel('Sudah bersih')
                    ->queries(
                        fn (Builder $query): Builder => $query->whereHas(
                            'outputEntries',
                            fn (Builder $entries): Builder => $entries->where('status', OutputStatus::SUBMITTED->value),
                        ),
                        fn (Builder $query): Builder => $query->whereDoesntHave(
                            'outputEntries',
                            fn (Builder $entries): Builder => $entries->where('status', OutputStatus::SUBMITTED->value),
                        ),
                    ),
            ])
            ->recordUrl(fn (Order $record): string => OutputReviewResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Belum ada output untuk diperiksa')
            ->emptyStateDescription('Order muncul di sini setelah penjahit melaporkan hasil kerjanya.')
            ->defaultSort('due_date');
    }

    /**
     * Ringkasan progres verifikasi satu order. Yang dihitung barisnya, bukan
     * komponennya, karena mandor memutuskan per laporan penjahit.
     */
    private static function verificationLabel(Order $record): string
    {
        $pending = (int) $record->pending_output_entries_count;
        $verified = (int) $record->verified_output_entries_count;

        if ($pending > 0) {
            return sprintf('%d menunggu', $pending);
        }

        if ($verified > 0) {
            return sprintf('%d terverifikasi', $verified);
        }

        return 'Belum ada output';
    }

    private static function verificationColor(Order $record): string
    {
        if ((int) $record->pending_output_entries_count > 0) {
            return 'warning';
        }

        return (int) $record->verified_output_entries_count > 0 ? 'success' : 'gray';
    }
}
