<?php

namespace App\Filament\Portal\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use App\Services\Finance\InvoiceService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Tabel invoice pada portal klien dengan aksi melihat rincian biaya dan konfirmasi pembayaran.
 */
class PortalInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('order.order_number')
                    ->label('Pesanan')
                    ->placeholder('-'),
                TextColumn::make('amount')
                    ->label('Total Tagihan')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('issued_at')
                    ->label('Tanggal Terbit')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('due_at')
                    ->label('Jatuh Tempo')
                    ->date('d/m/Y')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        InvoiceStatus::ISSUED->value => 'Terbit / Belum Bayar',
                        InvoiceStatus::PAYMENT_CONFIRMED->value => 'Menunggu Konfirmasi Finance',
                        InvoiceStatus::PAID->value => 'Lunas',
                    ]),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Rincian Biaya')
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->color('gray')
                    ->modalHeading(fn (Invoice $record): string => "Rincian Tagihan — {$record->invoice_number}")
                    ->modalDescription(null)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(function (Invoice $record): HtmlString {
                        // Use reflection to call private buildDetailHtml on InvoicesTable
                        $reflection = new \ReflectionClass(InvoicesTable::class);
                        $method = $reflection->getMethod('buildDetailHtml');
                        $method->setAccessible(true);

                        return new HtmlString($method->invoke(null, $record));
                    })
                    ->modalWidth('4xl'),

                Action::make('confirmPayment')
                    ->label('Konfirmasi Pembayaran')
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->color('success')
                    ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::ISSUED)
                    ->modalHeading(fn (Invoice $record): string => "Konfirmasi Pembayaran — {$record->invoice_number}")
                    ->modalDescription('Silakan isi informasi pembayaran yang telah Anda lakukan dan unggah bukti transfer.')
                    ->form([
                        Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options([
                                'Transfer Bank BCA' => 'Transfer Bank BCA',
                                'Transfer Bank Mandiri' => 'Transfer Bank Mandiri',
                                'Transfer Bank BNI' => 'Transfer Bank BNI',
                                'Transfer Bank BRI' => 'Transfer Bank BRI',
                                'Lainnya' => 'Lainnya / Tunai',
                            ])
                            ->default('Transfer Bank BCA')
                            ->required(),
                        TextInput::make('payment_notes')
                            ->label('Catatan Pengirim / No. Referensi Transfer')
                            ->placeholder('Contoh: Transfer via m-BCA atas nama PT Maju, Ref: 12345678')
                            ->required(),
                        FileUpload::make('payment_proof_path')
                            ->label('Unggah Bukti Transfer')
                            ->directory('payment_proofs')
                            ->image()
                            ->maxSize(5120)
                            ->helperText('Format JPG, PNG, maks 5 MB.'),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        app(InvoiceService::class)->confirmPayment($record, $data);

                        Notification::make()
                            ->title('Konfirmasi Pembayaran Terkirim')
                            ->body('Terima kasih. Pembayaran Anda telah kami terima dan sedang diverifikasi oleh tim Finance.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
