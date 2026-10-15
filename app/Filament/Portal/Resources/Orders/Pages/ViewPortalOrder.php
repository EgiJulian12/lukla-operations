<?php

namespace App\Filament\Portal\Resources\Orders\Pages;

use App\Enums\WorkScheme;
use App\Filament\Portal\Resources\Orders\PortalOrderResource;
use App\Models\Order;
use App\Services\Order\OrderQuotationService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPortalOrder extends ViewRecord
{
    protected static string $resource = PortalOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => PortalOrderResource::getUrl(panel: 'portal', tenant: auth()->user()?->client)),

            Action::make('approveQuote')
                ->label('Setuju')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (Order $record): bool => auth()->user()?->can('approveQuote', $record) ?? false)
                ->modalHeading('Konfirmasi Persetujuan Order')
                ->modalDescription('Pastikan tenggat waktu produksi, skema maklon, dan instruksi khusus sudah sesuai sebelum menyetujui penawaran.')
                ->fillForm(fn (Order $record): array => [
                    'due_date' => $record->due_date?->format('Y-m-d'),
                    'work_scheme' => $record->work_scheme?->value ?? WorkScheme::CMT->value,
                    'notes' => $record->notes,
                ])
                ->schema([
                    DatePicker::make('due_date')
                        ->label('Tenggat Produksi')
                        ->helperText('Konfirmasi atau sesuaikan estimasi tenggat waktu selesai produksi.')
                        ->required(),
                    Select::make('work_scheme')
                        ->label('Skema Maklon')
                        ->options(WorkScheme::class)
                        ->required(),
                    Textarea::make('notes')
                        ->label('Catatan Tambahan / Instruksi Khusus')
                        ->placeholder('Tuliskan catatan khusus atau instruksi terkait persetujuan pesanan...')
                        ->rows(3),
                ])
                ->action(function (Order $record, array $data): void {
                    app(OrderQuotationService::class)->approveQuote(
                        $record,
                        auth()->user(),
                        dueDate: $data['due_date'] ?? null,
                        workScheme: isset($data['work_scheme'])
                            ? ($data['work_scheme'] instanceof WorkScheme ? $data['work_scheme'] : WorkScheme::tryFrom((string) $data['work_scheme']))
                            : null,
                        notes: $data['notes'] ?? null,
                    );

                    Notification::make()
                        ->title('Penawaran Berhasil Disetujui')
                        ->body('Pesanan telah disetujui dan siap diproses lebih lanjut.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
