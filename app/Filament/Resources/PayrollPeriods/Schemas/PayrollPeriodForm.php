<?php

namespace App\Filament\Resources\PayrollPeriods\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Formulir periode payroll.
 *
 * Persentase uang makan dan tarif bonus QC adalah parameter kebijakan yang
 * diinput per periode, sehingga dapat berubah tanpa perubahan kode (FR-016).
 */
class PayrollPeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('period_start')
                    ->label('Awal Periode')
                    ->required(),
                DatePicker::make('period_end')
                    ->label('Akhir Periode')
                    ->required()
                    ->afterOrEqual('period_start'),
                TextInput::make('meal_allowance_percent')
                    ->label('Uang Makan')
                    ->numeric()
                    ->suffix('%')
                    ->minValue(0)
                    ->maxValue(100)
                    ->helperText('Persentase dari total dana ongkos jahit periode ini.'),
                TextInput::make('qc_bonus_rate_per_unit')
                    ->label('Tarif Bonus QC')
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(0)
                    ->helperText('Dibayarkan per unit yang lolos QC, lintas klien.'),
            ]);
    }
}
