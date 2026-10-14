<?php

namespace App\Filament\Portal\Resources\Orders\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

/**
 * Formulir permintaan klien: gambar design dan deskripsi.
 *
 * Klien tidak mengisi klien maupun jumlah unit — pemiliknya diambil dari akun
 * yang login, dan jumlah ditentukan belakangan saat penawaran disetujui.
 */
class PortalOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('design_images')
                    ->label('Gambar Design')
                    ->multiple()
                    ->image()
                    ->disk('s3')
                    ->directory('orders/designs')
                    ->reorderable()
                    ->required(),
                Textarea::make('description')
                    ->label('Deskripsi Permintaan')
                    ->helperText('Jelaskan model, bahan, dan detail lain yang diinginkan.')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
