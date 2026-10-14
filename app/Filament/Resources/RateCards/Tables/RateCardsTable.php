<?php

namespace App\Filament\Resources\RateCards\Tables;

use App\Enums\RateCardStatus;
use App\Enums\RateType;
use App\Models\RateCard;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar rate card per klien, dengan penanda tarif yang sedang berlaku dan aksi approval.
 *
 * Mendukung approval oleh Owner/Finance serta in-place update tanpa history.
 */
class RateCardsTable
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
                    ->placeholder('Semua artikel'),
                TextColumn::make('component.name')
                    ->label('Komponen')
                    ->placeholder('Semua komponen'),
                TextColumn::make('rate_type')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('rate')
                    ->label('Tarif')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('effective_from')
                    ->label('Berlaku Sejak')
                    ->date()
                    ->sortable(),
                TextColumn::make('effective_to')
                    ->label('Berlaku Sampai')
                    ->date()
                    ->placeholder('Masih berlaku'),
                IconColumn::make('is_currently_effective')
                    ->label('Aktif')
                    ->state(fn (RateCard $record): bool => $record->isCurrentlyEffective())
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(RateCardStatus::class),
                SelectFilter::make('rate_type')
                    ->label('Jenis')
                    ->options(RateType::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Setujui')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (RateCard $record): bool => $record->status !== RateCardStatus::APPROVED
                        && (bool) auth()->user()?->can('approve', $record))
                    ->action(function (RateCard $record): void {
                        $record->approve(auth()->user());
                    })
                    ->successNotificationTitle('Tarif disetujui'),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (RateCard $record): bool => $record->status === RateCardStatus::PENDING
                        && (bool) auth()->user()?->can('reject', $record))
                    ->form([
                        Textarea::make('reason')
                            ->label('Alasan Penolakan / Target Tarif')
                            ->required(),
                    ])
                    ->action(function (RateCard $record, array $data): void {
                        $record->reject($data['reason'], auth()->user());
                    })
                    ->successNotificationTitle('Tarif ditolak / minta revisi'),
                EditAction::make()
                    ->label('Ubah'),
            ])
            ->defaultSort('effective_from', 'desc');
    }
}
