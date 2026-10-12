<?php

namespace App\Filament\Resources\Samples\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Formulir sample.
 *
 * Status tidak diinput di sini: seluruh perpindahan status dijalankan oleh
 * SampleService, sehingga sample baru selalu lahir sebagai draf.
 */
class SampleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('article_id')
                    ->label('Artikel')
                    ->relationship('article', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Klien pemilik sample mengikuti artikel ini.'),
                TextInput::make('code')
                    ->label('Kode Sample')
                    ->required()
                    ->maxLength(255),
                TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->maxLength(255),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }
}
