<?php

namespace App\Filament\Resources\ApprovedSamples\Tables;

use App\Enums\OrderStatus;
use App\Enums\RateCardStatus;
use App\Enums\RateType;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Filament\Resources\ApprovedSamples\Actions\ConvertToOrderAction;
use App\Filament\Resources\ApprovedSamples\ApprovedSampleResource;
use App\Models\RateCard;
use App\Models\Sample;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tabel daftar sample yang telah disetujui (Approved) untuk kepala produksi dan owner.
 */
class ApprovedSamplesTable
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
                TextColumn::make('orders.work_scheme')
                    ->label('Skema Order')
                    ->badge()
                    ->placeholder('Belum Ada Order'),
                TextColumn::make('order_status')
                    ->label('Status Order')
                    ->state(fn (Sample $record): ?string => $record->orders()->latest('id')->first()?->status?->getLabel() ?? 'Belum Ada Order')
                    ->badge()
                    ->color(fn (Sample $record): string => match ($record->orders()->latest('id')->first()?->status) {
                        OrderStatus::READY_FOR_PRODUCTION => 'info',
                        OrderStatus::IN_PRODUCTION => 'warning',
                        OrderStatus::IN_QC => 'purple',
                        OrderStatus::COMPLETED => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('rate_card_status')
                    ->label('Status Tarif')
                    ->state(fn (Sample $record): string => $record->rateCardStatusLabel())
                    ->badge()
                    ->color(fn (Sample $record): string => $record->rateCardStatusColor()),
                TextColumn::make('total_rate')
                    ->label('Total Tarif')
                    ->state(function (Sample $record): string {
                        $total = $record->totalSewingRate();

                        return $total > 0 ? 'Rp '.number_format($total, 0, ',', '.') : '-';
                    })
                    ->fontFamily('mono')
                    ->sortable(false),
                TextColumn::make('approvedRevision.version_label')
                    ->label('Versi Disetujui')
                    ->badge()
                    ->color('success')
                    ->default('-'),
                TextColumn::make('components_count')
                    ->label('Komponen')
                    ->state(fn (Sample $record): int => (int) ($record->article?->components()->count() ?? 0))
                    ->numeric(),
                TextColumn::make('approvedRevision.approved_at')
                    ->label('Tanggal Disetujui')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('approvedRevision.approver.name')
                    ->label('Disetujui Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('work_scheme')
                    ->label('Skema Maklon Order')
                    ->options(WorkScheme::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('orders', fn (Builder $q) => $q->where('work_scheme', $data['value']))
                        : $query
                    ),
                SelectFilter::make('rate_card_status')
                    ->label('Status Tarif')
                    ->options([
                        'pending' => 'Menunggu Persetujuan',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak / Minta Revisi',
                        'empty' => 'Belum Diinput',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $val = $data['value'] ?? null;
                        if (! filled($val)) {
                            return $query;
                        }

                        if ($val === 'pending') {
                            return $query->whereHas('article.components.rateCards', fn (Builder $q) => $q->where('status', RateCardStatus::PENDING->value));
                        }
                        if ($val === 'rejected') {
                            return $query->whereHas('article.components.rateCards', fn (Builder $q) => $q->where('status', RateCardStatus::REJECTED->value));
                        }
                        if ($val === 'approved') {
                            return $query->whereHas('article.components.rateCards', fn (Builder $q) => $q->where('status', RateCardStatus::APPROVED->value));
                        }
                        if ($val === 'empty') {
                            return $query->whereDoesntHave('article.components.rateCards');
                        }

                        return $query;
                    }),
            ])
            ->recordUrl(fn (Sample $record): string => ApprovedSampleResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('view')
                    ->label('Buka Detail')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Sample $record): string => ApprovedSampleResource::getUrl('view', ['record' => $record])),
                Action::make('edit_rates')
                    ->label('Atur Tarif')
                    ->icon(Heroicon::OutlinedCurrencyDollar)
                    ->color('warning')
                    ->visible(fn (): bool => (bool) auth()->user()?->hasRoleSatisfying(
                        fn (UserRole $role): bool => $role === UserRole::PRODUCTION_LEAD,
                    ))
                    ->modalHeading('Atur Tarif Borongan Komponen')
                    ->modalDescription('Tarif yang ditentukan Kepala Produksi akan diajukan ke Owner untuk disetujui.')
                    ->modalWidth('md')
                    ->form(function (Sample $record): array {
                        $components = $record->article?->components ?? collect();
                        $fields = [];
                        foreach ($components as $component) {
                            $ts = $record->approvedRevision?->timeStudies()->where('component_id', $component->id)->first();
                            $tsLabel = $ts ? " (Time Study: {$ts->duration_seconds} dtk)" : ' (Belum diuji)';
                            $rateCard = RateCard::query()
                                ->withoutGlobalScope('client')
                                ->where('client_id', $record->client_id)
                                ->where('component_id', $component->id)
                                ->latest('effective_from')
                                ->first();

                            $fields[] = TextInput::make("rates.{$component->id}")
                                ->label("{$component->name}{$tsLabel}")
                                ->numeric()
                                ->prefix('Rp')
                                ->minValue(0)
                                ->default($rateCard ? (float) $rateCard->rate : 0)
                                ->required();
                        }
                        $fields[] = Textarea::make('notes')
                            ->label('Catatan Tarif')
                            ->placeholder('Catatan penetapan tarif atau spesifikasi pengerjaan...');

                        return $fields;
                    })
                    ->action(function (Sample $record, array $data): void {
                        foreach ($data['rates'] ?? [] as $componentId => $rate) {
                            $rateCard = RateCard::query()
                                ->withoutGlobalScope('client')
                                ->where('client_id', $record->client_id)
                                ->where('component_id', $componentId)
                                ->latest('effective_from')
                                ->first();

                            if ($rateCard) {
                                $rateCard->update([
                                    'rate' => $rate,
                                    'notes' => $data['notes'] ?? $rateCard->notes,
                                    'status' => RateCardStatus::PENDING,
                                    'approved_by' => null,
                                    'approved_at' => null,
                                ]);
                            } else {
                                RateCard::create([
                                    'client_id' => $record->client_id,
                                    'article_id' => $record->article_id,
                                    'component_id' => $componentId,
                                    'rate_type' => RateType::SEWING,
                                    'rate' => $rate,
                                    'status' => RateCardStatus::PENDING,
                                    'effective_from' => now()->toDateString(),
                                    'created_by' => auth()->id(),
                                    'notes' => $data['notes'] ?? null,
                                ]);
                            }
                        }

                        Notification::make()
                            ->title('Tarif borongan berhasil diajukan')
                            ->body('Status tarif kini Menunggu Persetujuan dari Owner.')
                            ->success()
                            ->send();
                    }),
                Action::make('approve_rates')
                    ->label('Setujui Rate Card')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->modalHeading('Detail Usulan Tarif Borongan (Rate Card)')
                    ->modalDescription(fn (Sample $record): string => "Periksa rincian tarif borongan komponen untuk {$record->title} ({$record->code}) sebelum menyetujui.")
                    ->modalWidth('xl')
                    ->modalContent(fn (Sample $record) => view('filament.resources.approved-samples.rate-card-review-modal', ['record' => $record]))
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan / Catatan Revisi')
                            ->placeholder('Tulis alasan penolakan jika memilih untuk menolak tarif...')
                            ->helperText('Hanya wajib diisi jika Anda memilih Tolak Tarif.')
                            ->rows(2),
                    ])
                    ->modalSubmitActionLabel('Setujui Tarif')
                    ->modalSubmitAction(fn ($action) => $action->color('success')->icon(Heroicon::OutlinedCheckCircle))
                    ->extraModalFooterActions(fn (Action $action): array => [
                        $action->makeModalSubmitAction('rejectRates', arguments: ['reject' => true])
                            ->label('Tolak Tarif')
                            ->color('danger')
                            ->icon(Heroicon::OutlinedXCircle),
                    ])
                    ->visible(fn (Sample $record): bool => (bool) auth()->user()?->hasRoleSatisfying(
                        fn (UserRole $role): bool => $role->canConfigureSystem() || $role->canManagePayroll(),
                    ) && $record->hasPendingRateCards())
                    ->action(function (Sample $record, array $data, array $arguments): void {
                        $isReject = (bool) ($arguments['reject'] ?? false);
                        $pendingRates = $record->latestComponentRateCards()->filter(fn ($rc) => $rc->status === RateCardStatus::PENDING);

                        if ($isReject) {
                            $reason = trim((string) ($data['rejection_reason'] ?? ''));
                            if (empty($reason)) {
                                $reason = 'Ditolak oleh Owner untuk penyesuaian tarif.';
                            }

                            foreach ($pendingRates as $rateCard) {
                                $rateCard->reject($reason, auth()->user());
                            }

                            Notification::make()
                                ->title('Tarif borongan ditolak / minta revisi')
                                ->body('Catatan penolakan telah dicatat untuk ditinjau ulang oleh Kepala Produksi.')
                                ->danger()
                                ->send();

                            return;
                        }

                        foreach ($pendingRates as $rateCard) {
                            $rateCard->approve(auth()->user());
                        }

                        Notification::make()
                            ->title('Tarif borongan disetujui')
                            ->body('Seluruh tarif borongan komponen berhasil disetujui untuk acuan produksi massal.')
                            ->success()
                            ->send();
                    }),
                ConvertToOrderAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
