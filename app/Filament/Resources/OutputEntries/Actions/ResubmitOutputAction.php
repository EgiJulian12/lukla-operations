<?php

namespace App\Filament\Resources\OutputEntries\Actions;

use App\Enums\OutputStatus;
use App\Models\OutputEntry;
use App\Services\Production\ProductionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Aksi "Ajukan Ulang" untuk baris output yang ditolak (FR-012, BR 4.5).
 */
class ResubmitOutputAction
{
    public static function make(): Action
    {
        return Action::make('resubmit')
            ->label('Ajukan Ulang')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (OutputEntry $record): bool => $record->status === OutputStatus::REJECTED
                && (bool) auth()->user()?->can('resubmit', $record))
            ->fillForm(fn (OutputEntry $record): array => [
                'quantity' => $record->quantity,
                'notes' => $record->notes,
            ])
            ->form([
                TextInput::make('quantity')
                    ->label('Jumlah Baru (pcs)')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                Textarea::make('notes')
                    ->label('Catatan Koreksi'),
            ])
            ->action(fn (OutputEntry $record, array $data) => app(ProductionService::class)
                ->resubmitOutput($record, (int) $data['quantity'], $data['notes'] ?? null))
            ->successNotificationTitle('Output berhasil diajukan ulang');
    }
}
