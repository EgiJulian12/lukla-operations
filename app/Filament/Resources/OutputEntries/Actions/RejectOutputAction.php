<?php

namespace App\Filament\Resources\OutputEntries\Actions;

use App\Enums\OutputStatus;
use App\Models\OutputEntry;
use App\Services\Production\ProductionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

/**
 * Aksi "Tolak" untuk satu baris output (FR-012).
 *
 * Alasan penolakan wajib diisi: catatan itulah yang dibaca penjahit saat tugasnya
 * terbuka kembali di daftar kerja, dan yang menjelaskan kenapa komponennya belum
 * dihitung selesai di halaman verifikasi order.
 */
class RejectOutputAction
{
    public static function make(): Action
    {
        return Action::make('reject')
            ->label('Tolak')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (OutputEntry $record): bool => $record->status === OutputStatus::SUBMITTED
                && (bool) auth()->user()?->can('reject', $record))
            ->form([
                Textarea::make('reason')
                    ->label('Alasan Penolakan')
                    ->required(),
            ])
            ->action(fn (OutputEntry $record, array $data) => app(ProductionService::class)
                ->rejectOutput($record, $data['reason'], auth()->user()))
            ->successNotificationTitle('Output ditolak');
    }
}
