<?php

namespace App\Filament\Resources\InventoryItems\Schemas;

use App\Enums\InventoryState;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Formulir item stok.
 *
 * Pesanan dan jumlah hanya dapat diisi saat penerimaan (create). Pada mode
 * ubah keduanya dinonaktifkan: memindahkan item ke pesanan lain akan
 * melanggar kepemilikan yang sudah terekam, dan mengubah jumlah langsung akan
 * memutus invarian "jumlah item = Σ pergerakan". Koreksi jumlah dilakukan
 * lewat pergerakan penyesuaian (stock opname).
 */
class InventoryItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->label('Pesanan')
                    ->relationship('order', 'order_number')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit')
                    ->helperText('Pemilik klien item mengikuti pesanan ini dan tidak dapat diubah setelah tercatat.'),
                Select::make('material_id')
                    ->label('Bahan')
                    ->relationship('material', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('batch')
                    ->label('Batch')
                    ->maxLength(255),
                Select::make('state')
                    ->label('Keadaan')
                    ->options(InventoryState::class)
                    ->default(InventoryState::RAW->value)
                    ->required(),
                TextInput::make('quantity')
                    ->label('Jumlah Diterima')
                    ->numeric()
                    ->minValue(0.01)
                    ->required()
                    ->visibleOn('create')
                    ->helperText('Jumlah awal penerimaan. Perubahan berikutnya hanya lewat pergerakan stok.'),
                TextInput::make('uom')
                    ->label('Satuan')
                    ->required()
                    ->default('meter')
                    ->maxLength(20),
                TextInput::make('location')
                    ->label('Lokasi')
                    ->maxLength(255),
            ]);
    }
}
