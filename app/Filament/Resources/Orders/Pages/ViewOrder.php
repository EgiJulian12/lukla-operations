<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\Order\OrderQuotationService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => OrderResource::getUrl('index')),
            EditAction::make()
                ->label('Ubah')
                ->visible(fn (): bool => (bool) auth()->user()?->can('update', $this->getRecord())),
            Action::make('rejectRequest')
                ->label('Tolak Pesanan')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => (bool) auth()->user()?->can('rejectRequest', $this->getRecord()))
                ->modalHeading('Tolak Permintaan Pesanan')
                ->modalDescription('Pesanan akan dibatalkan permanen dan tidak dapat diproses lebih lanjut.')
                ->schema([
                    Textarea::make('cancellation_reason')
                        ->label('Alasan Penolakan')
                        ->placeholder('Tuliskan alasan penolakan pesanan ini...')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    app(OrderQuotationService::class)->rejectRequest(
                        $this->getRecord(),
                        auth()->user(),
                        $data['cancellation_reason'],
                    );

                    Notification::make()
                        ->title('Pesanan Ditolak')
                        ->body('Pesanan telah dibatalkan dan ditutup.')
                        ->danger()
                        ->send();
                }),
        ];
    }
}
