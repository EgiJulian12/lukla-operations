<?php

namespace App\Filament\Resources\Components\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar komponen, dapat disaring per artikel.
 */
class ComponentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('article.name')
                    ->label('Artikel')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sequence')
                    ->label('Urutan')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('article_id')
                    ->label('Artikel')
                    ->relationship('article', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah'),
                DeleteAction::make()
                    ->label('Hapus'),
            ])
            ->defaultSort('article_id');
    }
}
