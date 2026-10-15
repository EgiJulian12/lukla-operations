<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\Actions\GenerateTelegramTokenAction;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->placeholder('(Staf Internal)')
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                IconColumn::make('telegram_linked')
                    ->label('Telegram')
                    ->icon(fn (User $record) => $record->telegram_chat_id !== null
                        ? Heroicon::OutlinedDevicePhoneMobile
                        : Heroicon::OutlinedXCircle
                    )
                    ->color(fn (User $record) => $record->telegram_chat_id !== null
                        ? 'success'
                        : 'gray'
                    )
                    ->tooltip(fn (User $record): string => $record->telegram_chat_id !== null
                        ? "Terhubung (ID: {$record->telegram_chat_id})"
                        : 'Belum terhubung'
                    )
                    ->getStateUsing(fn (User $record): bool => $record->telegram_chat_id !== null),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Status'),
                TernaryFilter::make('telegram_linked')
                    ->label('Telegram')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('telegram_chat_id'),
                        false: fn ($query) => $query->whereNull('telegram_chat_id'),
                    ),
            ])
            ->recordActions([
                GenerateTelegramTokenAction::make(),
                EditAction::make()->label('Ubah'),
            ])
            ->defaultSort('name');
    }
}
