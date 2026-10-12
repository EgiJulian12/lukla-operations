<?php

namespace App\Filament\Resources\WasteRecords\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Formulir sisa potongan (FR-008).
 *
 * Klien tidak diinput: diambil dari pesanan, sama seperti pergerakan stok,
 * supaya catatan efisiensi bahan selalu teratribusi ke pemilik yang benar.
 */
class WasteRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->label('Pesanan')
                    ->relationship('order', 'order_number')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Klien pemilik sisa potongan mengikuti pesanan ini.'),
                Select::make('material_id')
                    ->label('Bahan')
                    ->relationship('material', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->minValue(0.01)
                    ->required(),
                TextInput::make('uom')
                    ->label('Satuan')
                    ->required()
                    ->default('meter')
                    ->maxLength(20),
                TextInput::make('category')
                    ->label('Kategori')
                    ->maxLength(255),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }
}
