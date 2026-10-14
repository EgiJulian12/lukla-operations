<?php

namespace App\Filament\Resources\ApprovedSamples\Pages;

use App\Enums\RateCardStatus;
use App\Enums\RateType;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Filament\Resources\ApprovedSamples\ApprovedSampleResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Component;
use App\Models\RateCard;
use App\Models\Sample;
use App\Services\Sample\SampleService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Halaman detail penuh untuk Sample yang sudah disetujui.
 *
 * Menampilkan seluruh informasi sample, revisi terpilih, rincian komponen,
 * durasi time study (SMV), serta pemisahan tugas (SoD) penetapan tarif:
 * - Kepala Produksi: mengubah / menentukan usulan tarif borongan komponen (editRate).
 * - Finance & Owner: menyetujui (approveRate) atau menolak (rejectRate) usulan tarif.
 */
class ViewApprovedSample extends ViewRecord
{
    protected static string $resource = ApprovedSampleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('convert')
                ->label('Jadikan Order Produksi')
                ->icon(Heroicon::OutlinedTruck)
                ->color('success')
                ->visible(fn (): bool => (bool) auth()->user()?->can('convertToOrder', $this->getRecord()))
                ->form([
                    Select::make('work_scheme')
                        ->label('Skema Maklon')
                        ->options(WorkScheme::class)
                        ->default(WorkScheme::CMT->value)
                        ->required(),
                    TextInput::make('quantity')
                        ->label('Jumlah Produksi Massal')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    DatePicker::make('due_date')
                        ->label('Tenggat Selesai'),
                    Textarea::make('notes')
                        ->label('Catatan Order'),
                ])
                ->action(function (array $data): void {
                    /** @var Sample $sample */
                    $sample = $this->getRecord();

                    $scheme = $data['work_scheme'] instanceof WorkScheme
                        ? $data['work_scheme']
                        : WorkScheme::from($data['work_scheme']);

                    app(SampleService::class)->convertToOrder(
                        sample: $sample,
                        scheme: $scheme,
                        quantity: (int) $data['quantity'],
                        dueDate: $data['due_date'] ?? null,
                        notes: $data['notes'] ?? null,
                    );

                    $this->redirect(OrderResource::getUrl('index'));
                })
                ->successNotificationTitle('Order produksi massal berhasil dibuat dari sample disetujui'),
        ];
    }

    /**
     * Aksi untuk Kepala Produksi guna menentukan atau mengubah usulan tarif borongan per komponen.
     *
     * Finance dan Owner TIDAK diperkenankan mengedit harga langsung, melainkan menyetujui / menolak usulan.
     * Jika tarif diubah oleh Kepala Produksi, status tarif di-reset ke PENDING untuk approval ulang.
     */
    public function editRateAction(): Action
    {
        return Action::make('editRate')
            ->label('Atur Tarif Borongan')
            ->modalHeading('Atur Tarif Borongan Komponen')
            ->modalDescription('Tarif yang ditentukan Kepala Produksi akan diajukan ke Owner/Finance untuk disetujui (status: Menunggu Persetujuan).')
            ->modalWidth('md')
            ->visible(fn (): bool => (bool) auth()->user()?->hasRoleSatisfying(
                fn (UserRole $role): bool => $role === UserRole::PRODUCTION_LEAD,
            ))
            ->form([
                TextInput::make('component_name')
                    ->label('Nama Komponen')
                    ->disabled(),
                TextInput::make('time_study_duration')
                    ->label('Durasi Time Study (SMV)')
                    ->disabled()
                    ->helperText('Pedoman standar waktu pengerjaan penjahit.'),
                TextInput::make('rate')
                    ->label('Tarif Borongan (IDR)')
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(0)
                    ->required(),
                Textarea::make('notes')
                    ->label('Catatan Tarif')
                    ->placeholder('Catatan penetapan tarif atau rincian spesifikasi pengerjaan...'),
            ])
            ->fillForm(function (array $arguments): array {
                $componentId = $arguments['componentId'] ?? null;
                $component = Component::find($componentId);
                /** @var Sample $sample */
                $sample = $this->getRecord();

                $revision = $sample->approvedRevision;
                $ts = $revision?->timeStudies()->where('component_id', $componentId)->first();
                $durationText = $ts ? "{$ts->duration_seconds} detik (".round($ts->duration_seconds / 60, 2).' menit)' : 'Belum diuji';

                $rateCard = RateCard::query()
                    ->withoutGlobalScope('client')
                    ->where('client_id', $sample->client_id)
                    ->where('component_id', $componentId)
                    ->latest('effective_from')
                    ->first();

                return [
                    'component_name' => $component?->name ?? '-',
                    'time_study_duration' => $durationText,
                    'rate' => $rateCard ? (float) $rateCard->rate : 0,
                    'notes' => $rateCard?->notes,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $componentId = $arguments['componentId'] ?? null;
                /** @var Sample $sample */
                $sample = $this->getRecord();

                $rateCard = RateCard::query()
                    ->withoutGlobalScope('client')
                    ->where('client_id', $sample->client_id)
                    ->where('component_id', $componentId)
                    ->latest('effective_from')
                    ->first();

                if ($rateCard) {
                    $rateCard->update([
                        'rate' => $data['rate'],
                        'notes' => $data['notes'] ?? $rateCard->notes,
                        'status' => RateCardStatus::PENDING,
                        'approved_by' => null,
                        'approved_at' => null,
                    ]);
                } else {
                    RateCard::create([
                        'client_id' => $sample->client_id,
                        'article_id' => $sample->article_id,
                        'component_id' => $componentId,
                        'rate_type' => RateType::SEWING,
                        'rate' => $data['rate'],
                        'status' => RateCardStatus::PENDING,
                        'effective_from' => now()->toDateString(),
                        'created_by' => auth()->id(),
                        'notes' => $data['notes'] ?? null,
                    ]);
                }

                Notification::make()
                    ->title('Tarif borongan berhasil diperbarui')
                    ->body('Status tarif kini Menunggu Persetujuan dari Owner/Finance.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Aksi untuk Finance / Owner guna menyetujui (Approve) usulan tarif komponen dari Kepala Produksi.
     */
    public function approveRateAction(): Action
    {
        return Action::make('approveRate')
            ->label('Setujui Tarif')
            ->modalHeading('Setujui Tarif Borongan Komponen')
            ->modalDescription('Apakah Anda yakin ingin menyetujui tarif borongan ini untuk acuan produksi massal?')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (): bool => (bool) auth()->user()?->hasRoleSatisfying(
                fn (UserRole $role): bool => $role->canConfigureSystem() || $role->canManagePayroll(),
            ))
            ->action(function (array $arguments): void {
                $componentId = $arguments['componentId'] ?? null;
                /** @var Sample $sample */
                $sample = $this->getRecord();

                $rateCard = RateCard::query()
                    ->withoutGlobalScope('client')
                    ->where('client_id', $sample->client_id)
                    ->where('component_id', $componentId)
                    ->latest('effective_from')
                    ->first();

                if ($rateCard) {
                    $rateCard->approve(auth()->user());

                    Notification::make()
                        ->title('Tarif borongan disetujui')
                        ->body('Tarif Rp '.number_format((float) $rateCard->rate, 0, ',', '.').' telah disetujui dan aktif untuk produksi.')
                        ->success()
                        ->send();
                }
            });
    }

    /**
     * Aksi untuk Finance / Owner guna menolak (Reject) usulan tarif komponen dan meminta revisi.
     */
    public function rejectRateAction(): Action
    {
        return Action::make('rejectRate')
            ->label('Tolak Tarif')
            ->modalHeading('Tolak Tarif Borongan Komponen')
            ->modalDescription('Berikan alasan penolakan atau target tarif agar Kepala Produksi dapat melakukan penyesuaian.')
            ->modalWidth('md')
            ->color('danger')
            ->visible(fn (): bool => (bool) auth()->user()?->hasRoleSatisfying(
                fn (UserRole $role): bool => $role->canConfigureSystem() || $role->canManagePayroll(),
            ))
            ->form([
                Textarea::make('reason')
                    ->label('Alasan Penolakan / Target Tarif Revisi')
                    ->placeholder('Contoh: Tarif melebihi plafon CMT, mohon sesuaikan ke kisaran Rp 2.200...')
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $componentId = $arguments['componentId'] ?? null;
                /** @var Sample $sample */
                $sample = $this->getRecord();

                $rateCard = RateCard::query()
                    ->withoutGlobalScope('client')
                    ->where('client_id', $sample->client_id)
                    ->where('component_id', $componentId)
                    ->latest('effective_from')
                    ->first();

                if ($rateCard) {
                    $rateCard->reject($data['reason'], auth()->user());

                    Notification::make()
                        ->title('Tarif borongan ditolak / minta revisi')
                        ->body('Catatan penolakan telah dicatat untuk ditinjau Kepala Produksi.')
                        ->danger()
                        ->send();
                }
            });
    }
}
