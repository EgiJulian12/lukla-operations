<?php

namespace App\Filament\Resources\MaterialDispatches\Schemas;

use App\Models\InventoryItem;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MaterialDispatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('inventory_item_id')
                    ->label('Item Bahan / Aksesoris Gudang')
                    ->options(fn (): array => InventoryItem::query()
                        ->with(['client', 'order', 'material'])
                        ->where('quantity', '>', 0)
                        ->get()
                        ->mapWithKeys(fn (InventoryItem $item): array => [
                            $item->getKey() => sprintf(
                                '%s — %s (Pesanan #%s, Klien: %s, Stok: %s %s)',
                                $item->tag_id,
                                $item->material?->name ?? 'Bahan / Aksesoris',
                                $item->order?->order_number ?? '-',
                                $item->client?->name ?? '-',
                                number_format((float) $item->quantity, 2),
                                $item->uom,
                            ),
                        ])
                        ->all())
                    ->searchable()
                    ->required()
                    ->live(),
                TextInput::make('quantity')
                    ->label('Jumlah Permintaan')
                    ->numeric()
                    ->minValue(0.01)
                    ->required()
                    ->helperText('Jumlah bahan/aksesoris yang akan dikirimkan ke bagian produksi.'),
                Textarea::make('notes')
                    ->label('Catatan Pengiriman')
                    ->placeholder('Misal: Untuk cutting pola badan depan dan belakang')
                    ->columnSpanFull(),
            ]);
    }
}
