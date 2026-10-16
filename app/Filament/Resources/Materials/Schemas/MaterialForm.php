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
                    ->label('Nama Bahan / Aksesoris')
                    ->placeholder('Contoh: Kain Katun Combed 30s, Resleting YKK 50cm, Kancing')
                    ->required()
                    ->maxLength(255),
                Select::make('client_id')
                    ->label('Pemilik Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Kosongkan untuk bahan/aksesoris generik yang dipakai lintas klien.'),
                TextInput::make('uom')
                    ->label('Satuan')
                    ->required()
                    ->default('meter')
                    ->helperText('Contoh: meter, yard, pcs, lusin, roll')
                    ->maxLength(20),
                TextInput::make('category')
                    ->label('Kategori')
                    ->placeholder('Kain Utama, Aksesoris, Benang, Kancing, Zipper')
                    ->maxLength(255),
            ]);
    }
}
