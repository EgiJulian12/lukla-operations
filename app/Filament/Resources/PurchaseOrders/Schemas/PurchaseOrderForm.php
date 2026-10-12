<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\Material;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Formulir PO bahan.
 *
 * Status tidak diinput di sini: PO selalu lahir sebagai draf dan berubah
 * menjadi disetujui lewat aksi terpisah yang berwenang.
 */
class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('po_number')
                    ->label('Nomor PO')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('supplier')
                    ->label('Pemasok')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('ordered_at')
                    ->label('Tanggal Pesan')
                    ->default(now()),
                DatePicker::make('expected_at')
                    ->label('Perkiraan Datang'),
                Repeater::make('items')
                    ->relationship()
                    ->label('Rincian Bahan')
                    ->schema([
                        Select::make('material_id')
                            ->label('Bahan')
                            ->options(fn (): array => Material::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                        TextInput::make('unit_price')
                            ->label('Harga Satuan')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp')
                            ->required(),
                    ])
                    ->columns(3)
                    ->addActionLabel('Tambah Bahan')
                    ->defaultItems(1)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }
}
