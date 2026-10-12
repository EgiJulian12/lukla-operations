<?php

namespace App\Filament\Resources\PayrollPeriods\Tables;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar periode payroll dengan aksi hitung, setujui, dan tandai dibayar
 * (FR-015, FR-026).
 *
 * Setiap aksi hanya muncul pada keadaan yang tepat dan bagi pengguna yang
 * berwenang, sehingga urutan hitung, setujui, dan bayar tidak dapat dilompati.
 */
class PayrollPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period_start')
                    ->label('Awal')
                    ->date()
                    ->sortable(),
                TextColumn::make('period_end')
                    ->label('Akhir')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('meal_allowance_percent')
                    ->label('Uang Makan')
                    ->suffix('%')
                    ->placeholder('-'),
                TextColumn::make('qc_bonus_rate_per_unit')
                    ->label('Tarif Bonus QC')
                    ->money('IDR')
                    ->placeholder('-'),
                TextColumn::make('total_sewing_cost')
                    ->label('Total Ongkos Jahit')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('entries_count')
                    ->label('Penjahit')
                    ->counts('entries')
                    ->numeric(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(PayrollPeriodStatus::class),
            ])
            ->recordActions([
                Action::make('calculate')
                    ->label('Hitung')
                    ->icon(Heroicon::OutlinedCalculator)
                    ->color('warning')
                    ->visible(fn (PayrollPeriod $record): bool => (bool) auth()->user()?->can('calculate', $record))
                    ->form([
                        Select::make('recipients')
                            ->label('Penerima Bonus QC')
                            ->multiple()
                            ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->default(fn (): array => PayrollService::defaultBonusRecipients())
                            ->searchable()
                            ->helperText('Kosongkan untuk memakai penerima default: dua mandor dan satu kepala operasional.'),
                    ])
                    ->action(fn (PayrollPeriod $record, array $data) => app(PayrollService::class)
                        ->calculate($record, $data['recipients'] ?? []))
                    ->successNotificationTitle('Payroll dihitung'),
                Action::make('approve')
                    ->label('Setujui')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PayrollPeriod $record): bool => (bool) auth()->user()?->can('approve', $record))
                    ->action(fn (PayrollPeriod $record) => app(PayrollService::class)->approve($record))
                    ->successNotificationTitle('Payroll disetujui'),
                Action::make('pay')
                    ->label('Tandai Dibayar')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (PayrollPeriod $record): bool => (bool) auth()->user()?->can('pay', $record))
                    ->action(fn (PayrollPeriod $record) => app(PayrollService::class)->markPaid($record))
                    ->successNotificationTitle('Payroll ditandai dibayar'),
                EditAction::make()
                    ->label('Ubah'),
            ])
            ->defaultSort('period_start', 'desc');
    }
}
