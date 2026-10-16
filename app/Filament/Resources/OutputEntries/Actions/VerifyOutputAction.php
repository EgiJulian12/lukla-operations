<?php

namespace App\Filament\Resources\OutputEntries\Actions;

use App\Enums\OutputStatus;
use App\Models\OutputEntry;
use App\Services\Production\ProductionService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Aksi "Verifikasi" untuk satu baris output (FR-012).
 *
 * Dipakai bersama tabel output mandor dan daftar output pada halaman detail
 * order, sehingga syarat "hanya yang menunggu verifikasi" dan wewenangnya
 * identik di kedua tempat. Wewenang tetap ditanyakan ke policy, bukan diperiksa
 * ulang lewat peran, agar separasi tugas tetap punya satu definisi.
 */
class VerifyOutputAction
{
    public static function make(): Action
    {
        return Action::make('verify')
            ->label('Verifikasi')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (OutputEntry $record): bool => $record->status === OutputStatus::SUBMITTED
                && (bool) auth()->user()?->can('verify', $record))
            ->action(fn (OutputEntry $record) => app(ProductionService::class)
                ->verifyOutput($record, auth()->user()))
            ->successNotificationTitle('Output terverifikasi');
    }
}
