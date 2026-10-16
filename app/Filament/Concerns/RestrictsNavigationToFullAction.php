<?php

namespace App\Filament\Concerns;

use App\Models\User;
use App\Support\RoleMenuAccess;

trait RestrictsNavigationToFullAction
{
    /**
     * Hanya mendaftarkan resource ini ke sidebar panel /admin jika pengguna
     * memiliki hak akses penuh (Full Action / Manage) pada resource ini.
     */
    public static function shouldRegisterNavigation(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return RoleMenuAccess::canAccess($user, static::class);
    }
}
