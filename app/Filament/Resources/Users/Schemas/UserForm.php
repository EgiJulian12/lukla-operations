<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\Client;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * Edit form for user accounts.
 *
 * Allows admin to manage name, email, active status, client assignment,
 * and manually clear the telegram_chat_id if needed.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Select::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Kosongkan untuk staf internal.'),
                Select::make('roles')
                    ->label('Peran')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->options(
                        collect(UserRole::cases())
                            ->mapWithKeys(fn (UserRole $role): array => [
                                $role->value => $role->getLabel(),
                            ])
                            ->all()
                    )
                    ->preload(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
                TextInput::make('telegram_chat_id')
                    ->label('Telegram Chat ID')
                    ->nullable()
                    ->helperText('Kosongkan untuk memutus koneksi Telegram. Diisi otomatis saat klien mendaftar via bot.')
                    ->maxLength(255),
            ]);
    }
}
