<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Jenis pemilik order: brand internal (Snøhund) atau klien maklon B2B.
 */
enum ClientType: string implements HasLabel
{
    case INTERNAL = 'internal';
    case MAKLON = 'maklon';

    public function getLabel(): string
    {
        return match ($this) {
            self::INTERNAL => 'Internal (Snøhund)',
            self::MAKLON => 'Klien Maklon B2B',
        };
    }

    public function isInternal(): bool
    {
        return $this === self::INTERNAL;
    }
}
