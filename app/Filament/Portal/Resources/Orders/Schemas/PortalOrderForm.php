<?php

namespace App\Filament\Portal\Resources\Orders\Schemas;

use App\Enums\WorkScheme;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Formulir permintaan klien: gambar design, deskripsi, jumlah order, tenggat estimasi, dan skema maklon.
 */
class PortalOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('quantity')
                    ->label('Jumlah Order (pcs)')
                    ->helperText('Perkiraan jumlah unit pakaian yang akan diproduksi.')
                    ->numeric()
                    ->minValue(1)
                    ->default(100)
                    ->required(),
                DatePicker::make('start_date')
                    ->label('Perkiraan Mulai Project')
                    ->helperText('Estimasi tanggal project mulai dikerjakan.')
                    ->native(false)
                    ->minDate(now()->startOfDay())
                    ->required(),
                DatePicker::make('due_date')
                    ->label('Tenggat Selesai')
                    ->helperText('Estimasi tenggat waktu pesanan harus selesai diproduksi.')
                    ->native(false)
                    ->afterOrEqual('start_date')
                    ->required(),
                Select::make('work_scheme')
                    ->label('Skema Kerja Sama')
                    ->options(WorkScheme::class)
                    ->default(WorkScheme::CMT)
                    ->helperText('Pilih skema maklon: CMT, Semi-CMT, atau Full Package.')
                    ->required(),
                FileUpload::make('design_images')
                    ->label('Gambar Design')
                    ->multiple()
                    ->image()
                    ->disk(config('filesystems.default', 'local'))
                    ->directory('orders/designs')
                    ->reorderable()
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Deskripsi Permintaan')
                    ->helperText('Jelaskan model, bahan, dan detail lain yang diinginkan.')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
