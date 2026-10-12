<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Enums\ClientType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * Formulir data klien.
 */
class ClientForm
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
                Select::make('type')
                    ->label('Jenis')
                    ->options(ClientType::class)
                    ->default(ClientType::MAKLON->value)
                    ->required()
                    ->helperText('Pilih Internal untuk brand Snøhund.'),
                TextInput::make('contact_person')
                    ->label('Narahubung')
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Telepon')
                    ->tel()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
                Textarea::make('address')
                    ->label('Alamat')
                    ->columnSpanFull(),
            ]);
    }
}
