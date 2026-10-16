<?php

namespace App\Filament\Portal\Widgets;

use App\Filament\Widgets\TelegramConnectionWidget;

/**
 * Widget Telegram untuk portal klien, sama dengan widget panel admin.
 */
class PortalTelegramConnectionWidget extends TelegramConnectionWidget
{
    protected string $view = 'filament.widgets.telegram-connection';
}
