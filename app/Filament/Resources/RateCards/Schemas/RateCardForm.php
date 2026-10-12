<?php

namespace App\Filament\Resources\RateCards\Schemas;

use App\Enums\RateType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Formulir rate card.
 *
 * Artikel dan komponen boleh dikosongkan: tarif paling umum adalah tarif dasar
 * klien, tarif artikel menengah, dan tarif komponen paling spesifik.
 */
class RateCardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('article_id')
                    ->label('Artikel')
                    ->relationship('article', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Kosongkan untuk tarif dasar klien yang berlaku untuk semua artikel.'),
                Select::make('component_id')
                    ->label('Komponen')
                    ->relationship('component', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Kosongkan untuk tarif tingkat artikel.'),
                Select::make('rate_type')
                    ->label('Jenis Tarif')
                    ->options(RateType::class)
                    ->default(RateType::SEWING->value)
                    ->required(),
                TextInput::make('rate')
                    ->label('Tarif')
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(0)
                    ->required(),
                DatePicker::make('effective_from')
                    ->label('Berlaku Sejak')
                    ->default(now())
                    ->required(),
                DatePicker::make('effective_to')
                    ->label('Berlaku Sampai')
                    ->helperText('Kosongkan selama tarif ini masih berlaku.'),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }
}
