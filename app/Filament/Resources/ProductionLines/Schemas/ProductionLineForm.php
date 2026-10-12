<?php

namespace App\Filament\Resources\ProductionLines\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * Formulir line produksi.
 */
class ProductionLineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('capacity')
                    ->label('Kapasitas Mesin')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Indikator jumlah mesin, bukan batas jumlah penjahit yang dapat dialokasikan.'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
