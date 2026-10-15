<?php

namespace App\Filament\Resources\Samples\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Samples\SampleResource;
use App\Models\Order;
use App\Services\Sample\SampleService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ListSamples extends ListRecords
{
    protected static string $resource = SampleResource::class;

    protected function getHeaderActions(): array
    {
        $queueCount = Order::query()
            ->withoutGlobalScope('client')
            ->where('status', OrderStatus::QUOTE_APPROVED->value)
            ->whereNull('article_id')
            ->count();

        return [
            Action::make('takeFromOrder')
                ->label('Ambil dari Order Di-ACC'.($queueCount > 0 ? " ({$queueCount})" : ''))
                ->icon(Heroicon::OutlinedSparkles)
                ->color($queueCount > 0 ? 'warning' : 'gray')
                ->modalHeading('Mulai Sample dari Order yang Disetujui Klien')
                ->modalDescription('Pilih order yang telah disetujui klien untuk dibuatkan Artikel & Sample v1 secara otomatis.')
                ->form([
                    Select::make('order_id')
                        ->label('Pilih Order Di-ACC')
                        ->options(function (): array {
                            return Order::query()
                                ->withoutGlobalScope('client')
                                ->where('status', OrderStatus::QUOTE_APPROVED->value)
                                ->whereNull('article_id')
                                ->with('client')
                                ->get()
                                ->mapWithKeys(fn (Order $o): array => [
                                    $o->getKey() => sprintf(
                                        '%s — %s (%s)',
                                        $o->order_number,
                                        $o->client?->name ?? 'Klien',
                                        Str::limit($o->description ?? 'Tanpa deskripsi', 50)
                                    ),
                                ])
                                ->all();
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (mixed $state, callable $set): void {
                            if (! $state) {
                                return;
                            }
                            $order = Order::find($state);
                            if ($order) {
                                $set('name', Str::limit(trim((string) $order->description), 80, '') ?: 'Artikel '.$order->order_number);
                                $set('code', $order->order_number);
                                $set('description', $order->description);
                            }
                        }),
                    TextInput::make('name')
                        ->label('Nama Artikel')
                        ->required(),
                    TextInput::make('code')
                        ->label('Kode / SKU Artikel')
                        ->required(),
                    Textarea::make('description')
                        ->label('Deskripsi / Catatan'),
                ])
                ->action(function (array $data) {
                    $order = Order::findOrFail($data['order_id']);
                    $sample = app(SampleService::class)->initializeSampleFromOrder($order, $data);

                    Notification::make()
                        ->title('Sample dan Artikel berhasil dibuat')
                        ->success()
                        ->send();

                    return redirect(SampleResource::getUrl('edit', ['record' => $sample->getKey()]));
                }),
        ];
    }
}
