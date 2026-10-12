<?php

namespace App\Filament\Resources\Materials\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Formulir bahan.
 *
 * Klien boleh dikosongkan: bahan generik dipakai lintas klien, sedangkan bahan
 * titipan harus menyebut pemiliknya agar aturan anti-kontaminasi silang dapat
 * menolak pengeluaran ke order klien lain.
 */
class MaterialForm
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
                Select::make('client_id')
                    ->label('Pemilik Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Kosongkan untuk bahan generik yang dipakai lintas klien.'),
                TextInput::make('uom')
                    ->label('Satuan')
                    ->required()
                    ->default('meter')
                    ->maxLength(20),
                TextInput::make('category')
                    ->label('Kategori')
                    ->maxLength(255),
            ]);
    }
}
