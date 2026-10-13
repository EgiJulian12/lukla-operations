<?php

namespace App\Filament\Portal\Widgets;

use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Order;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Widget chat AI untuk portal klien.
 *
 * Menyematkan komponen ChatModal dengan konteks klien yang sedang login,
 * sehingga asisten dapat memberikan jawaban yang relevan dengan order,
 * inventory, dan QC milik klien tersebut.
 */
class PortalChatWidget extends Widget
{
    protected string $view = 'filament.portal.widgets.portal-chat-widget';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    /** @var array<string, mixed> */
    public array $clientContext = [];

    public function mount(): void
    {
        /** @var Client|null $client */
        $client = Filament::getTenant();

        if (! $client) {
            return;
        }

        $activeOrderCount = Order::query()
            ->where('client_id', $client->id)
            ->whereNotIn('status', [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value])
            ->count();

        $this->clientContext = [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_code' => $client->code,
            'client_type' => $client->type?->value,
            'active_orders' => $activeOrderCount,
        ];
    }
}
