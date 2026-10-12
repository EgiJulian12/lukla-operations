<?php

namespace App\Filament\Resources\OverheadRecords\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Formulir biaya overhead.
 *
 * Bulan diambil dari tanggal yang dipilih (hari apa pun dalam bulan itu),
 * sehingga petugas tidak perlu mengingat tanggal pertama.
 */
class OverheadRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('period_month')
                    ->label('Bulan')
                    ->default(now())
                    ->required()
                    ->helperText('Pilih tanggal mana pun pada bulan yang dimaksud.'),
                TextInput::make('category')
                    ->label('Kategori')
                    ->required()
                    ->datalist(['Listrik', 'Air', 'Internet', 'Operasional'])
                    ->maxLength(255),
                TextInput::make('amount')
                    ->label('Jumlah')
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(0)
                    ->required(),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }
}
