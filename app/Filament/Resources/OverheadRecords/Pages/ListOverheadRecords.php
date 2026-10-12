<?php

namespace App\Filament\Resources\OverheadRecords\Pages;

use App\Filament\Resources\OverheadRecords\OverheadRecordResource;
use App\Models\OverheadAllocation;
use App\Services\Payroll\PayrollService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

/**
 * Daftar biaya overhead dengan aksi perhitungan overhead per unit (FR-018).
 *
 * Hasil perhitungan disimpan pada OverheadAllocation agar laporan bulan lalu
 * tidak ikut berubah ketika data produksi sesudahnya diperbarui.
 */
class ListOverheadRecords extends ListRecords
{
    protected static string $resource = OverheadRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Biaya'),
            Action::make('allocate')
                ->label('Hitung Overhead per Unit')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('warning')
                ->form([
                    DatePicker::make('month')
                        ->label('Bulan')
                        ->default(now())
                        ->required()
                        ->helperText('Pilih tanggal mana pun pada bulan yang ingin dihitung.'),
                ])
                ->action(function (array $data): void {
                    $allocation = app(PayrollService::class)->allocateOverhead($data['month']);

                    Notification::make()
                        ->title('Overhead per unit dihitung')
                        ->body(sprintf(
                            '%s: total %s dibagi %d unit, hasil %s per unit.',
                            $allocation->period_month->translatedFormat('F Y'),
                            number_format((float) $allocation->total_overhead, 0, ',', '.'),
                            $allocation->total_units,
                            number_format((float) $allocation->overhead_per_unit, 4, ',', '.'),
                        ))
                        ->success()
                        ->send();
                }),
        ];
    }
}
