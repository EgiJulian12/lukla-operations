<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Telegram\TelegramBindingService;
use Filament\Widgets\Widget;

/**
 * Status penautan akun Telegram untuk user yang sedang login, plus tombol
 * pembuatan deep link pairing sekali-pakai (berlaku 15 menit).
 */
class TelegramConnectionWidget extends Widget
{
    protected string $view = 'filament.widgets.telegram-connection';

    public ?string $linkUrl = null;

    public function mount(): void
    {
        // Link dibuat hanya saat user menekan tombol, bukan tiap kali widget render.
    }

    public function isLinked(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->telegram_chat_id !== null;
    }

    public function generateLink(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->linkUrl = app(TelegramBindingService::class)->startUrlFor($user);
    }
}
