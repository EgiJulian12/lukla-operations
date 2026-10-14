<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\WorkScheme;
use App\Models\Order;
use App\Services\Order\OrderQuotationService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar order lintas klien, dari permintaan klien sampai produksi massal.
 *
 * Owner memberi penawaran pada permintaan klien dan mengirim permintaan sample
 * setelah penawaran disetujui. Filter status "Penawaran Disetujui" adalah daftar
 * order yang siap masuk tahap sample.
 */
class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Nomor Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('work_scheme')
                    ->label('Skema')
                    ->badge(),
                TextColumn::make('description')
                    ->label('Permintaan')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('estimated_cost')
                    ->label('Penawaran')
                    ->money('IDR')
                    ->placeholder('Belum ditawar')
                    ->sortable(),
                TextColumn::make('sample.code')
                    ->label('Sample')
                    ->placeholder('-')
                    ->description(fn (Order $record): ?string => $record->sampleRevision !== null
                        ? 'Revisi v'.$record->sampleRevision->version
                        : null),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('due_date')
                    ->label('Tenggat')
                    ->date()
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('work_scheme')
                    ->label('Skema')
                    ->options(WorkScheme::class),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(OrderStatus::class),
            ])
            ->recordActions([
                Action::make('provideQuote')
                    ->label('Berikan Penawaran')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('warning')
                    ->modalHeading('Penawaran untuk permintaan klien')
                    ->visible(fn (Order $record): bool => auth()->user()?->can('quote', $record) ?? false)
                    ->fillForm(fn (Order $record): array => [
                        'estimated_cost' => $record->estimated_cost,
                        'quote_notes' => $record->quote_notes,
                    ])
                    ->schema([
                        // Entri ini murni tampilan — dehydrated(false) menjaga datanya
                        // tidak ikut terkirim sebagai isi form penawaran.
                        Section::make('Permintaan Klien')
                            ->description('Permintaan asli klien — periksa sebelum menetapkan biaya.')
                            ->schema([
                                TextEntry::make('description')
                                    ->label('Deskripsi')
                                    ->placeholder('-')
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                                // Bucket s3 privat — biarkan Filament menerbitkan URL bertanda tangan.
                                ImageEntry::make('design_images')
                                    ->label('Gambar Design')
                                    ->disk('s3')
                                    ->placeholder('Klien tidak melampirkan gambar.')
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        TextInput::make('estimated_cost')
                            ->label('Perkiraan Biaya (IDR)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Textarea::make('quote_notes')
                            ->label('Catatan Penawaran')
                            ->columnSpanFull(),
                    ])
                    ->action(function (Order $record, array $data): void {
                        app(OrderQuotationService::class)->provideQuote(
                            $record,
                            (float) $data['estimated_cost'],
                            $data['quote_notes'] ?? null,
                            auth()->user(),
                        );

                        Notification::make()
                            ->title('Penawaran dikirim ke klien')
                            ->success()
                            ->send();
                    }),
                Action::make('sendSampleRequest')
                    ->label('Kirim Permintaan Sample')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim permintaan sample?')
                    ->modalDescription('Sample akan dibuat dari gambar design dan deskripsi klien, lalu diteruskan ke Tim Sample.')
                    ->visible(fn (Order $record): bool => auth()->user()?->can('requestSample', $record) ?? false)
                    ->action(function (Order $record): void {
                        app(OrderQuotationService::class)->requestSample($record, auth()->user());

                        Notification::make()
                            ->title('Permintaan sample dibuat')
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->label('Ubah'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
