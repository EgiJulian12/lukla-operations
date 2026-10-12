<?php

namespace App\Filament\Resources\Samples\Tables;

use App\Enums\SampleStatus;
use App\Enums\WorkScheme;
use App\Models\Sample;
use App\Services\Sample\SampleService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar sample beserta aksi konversinya ke order massal (FR-004).
 */
class SamplesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('article.name')
                    ->label('Artikel')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('revisions_count')
                    ->label('Revisi')
                    ->counts('revisions')
                    ->numeric(),
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
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(SampleStatus::class),
            ])
            ->recordActions([
                Action::make('convert')
                    ->label('Jadikan Order')
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('success')
                    ->visible(fn (Sample $record): bool => (bool) auth()->user()?->can('convertToOrder', $record))
                    ->form([
                        Select::make('work_scheme')
                            ->label('Skema Maklon')
                            ->options(WorkScheme::class)
                            ->default(WorkScheme::SEMI_CMT->value)
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Jumlah Produksi')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        DatePicker::make('due_date')
                            ->label('Tenggat'),
                        Textarea::make('notes')
                            ->label('Catatan'),
                    ])
                    ->action(function (Sample $record, array $data): void {
                        $scheme = $data['work_scheme'] instanceof WorkScheme
                            ? $data['work_scheme']
                            : WorkScheme::from($data['work_scheme']);

                        app(SampleService::class)->convertToOrder(
                            sample: $record,
                            scheme: $scheme,
                            quantity: (int) $data['quantity'],
                            dueDate: $data['due_date'] ?? null,
                            notes: $data['notes'] ?? null,
                        );
                    })
                    ->successNotificationTitle('Order produksi massal dibuat'),
                EditAction::make()
                    ->label('Ubah'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
