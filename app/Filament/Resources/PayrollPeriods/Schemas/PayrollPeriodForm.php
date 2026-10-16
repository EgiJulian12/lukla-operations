<?php

namespace App\Filament\Resources\PayrollPeriods\Schemas;

use App\Models\PayrollPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

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
                    ->default(fn () => now()->startOfWeek())
                    ->helperText('Tanggal awal rentang kerja penjahit yang akan dihitung upahnya (default: Senin).')
                    ->required()
                    ->rules([
                        fn (Get $get, ?PayrollPeriod $record): Unique => Rule::unique('payroll_periods', 'period_start')
                            ->where('period_end', $get('period_end'))
                            ->when($record, fn ($rule) => $rule->ignore($record->id)),
                    ])
                    ->validationMessages([
                        'unique' => 'Periode dengan tanggal awal dan akhir yang sama sudah ada.',
                    ]),
                DatePicker::make('period_end')
                    ->label('Akhir Periode')
                    ->default(fn () => now()->endOfWeek())
                    ->helperText('Tanggal akhir rentang kerja penjahit (default: Minggu).')
                    ->required()
                    ->afterOrEqual('period_start'),
                TextInput::make('meal_allowance_percent')
                    ->label('Uang Makan')
                    ->numeric()
                    ->suffix('%')
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(10)
                    ->helperText('Persentase dari total dana ongkos jahit periode ini untuk dialokasikan ke uang makan.'),
                TextInput::make('qc_bonus_rate_per_unit')
                    ->label('Tarif Bonus QC')
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(0)
                    ->helperText('Dibayarkan per unit yang lolos QC, lintas klien.'),
            ]);
    }
}
