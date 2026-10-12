<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Formulir invoice klien.
 *
 * Status tidak diinput di sini: invoice lahir sebagai draf dan diterbitkan
 * lewat aksi terpisah yang berwenang.
 */
class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('order_id')
                    ->label('Order')
                    ->relationship('order', 'order_number')
                    ->searchable()
                    ->preload()
                    ->placeholder('Opsional'),
                TextInput::make('amount')
                    ->label('Nilai Tagihan')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('Rp')
                    ->required(),
                DatePicker::make('issued_at')
                    ->label('Tanggal Terbit')
                    ->helperText('Kosongkan agar diisi otomatis saat invoice diterbitkan.'),
                DatePicker::make('due_at')
                    ->label('Jatuh Tempo'),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }
}
