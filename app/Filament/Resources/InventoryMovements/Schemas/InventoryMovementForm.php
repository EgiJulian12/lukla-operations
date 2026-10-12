<?php

namespace App\Filament\Resources\InventoryMovements\Schemas;

use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\Order;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Formulir pergerakan stok.
 *
 * Klien dan pesanan TIDAK diinput di sini — keduanya diambil dari item stok
 * yang dipilih, sehingga petugas gudang tidak punya jalan untuk menyatakan
 * kepemilikan yang berbeda dari barang yang benar-benar dikeluarkan.
 *
 * Untuk pengeluaran dan transfer, petugas memilih PESANAN TUJUAN. Bila tujuan
 * itu bukan pemilik item, sistem menolak dengan peringatan eksplisit (FR-007,
 * NFR-002) — lihat CreateInventoryMovement.
 */
class InventoryMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('inventory_item_id')
                    ->label('Item Stok')
                    ->options(fn (): array => self::itemOptions())
                    ->searchable()
                    ->required()
                    ->live()
                    ->helperText(fn (Get $get): ?string => self::ownershipHint($get('inventory_item_id'))),
                Select::make('movement_type')
                    ->label('Jenis Pergerakan')
                    ->options(MovementType::class)
                    ->default(MovementType::RECEIPT->value)
                    ->required()
                    ->live(),
                Select::make('destination_order_id')
                    ->label('Pesanan Tujuan')
                    ->options(fn (): array => self::orderOptions())
                    ->searchable()
                    ->visible(fn (Get $get): bool => self::requiresDestination($get('movement_type')))
                    ->required(fn (Get $get): bool => self::requiresDestination($get('movement_type')))
                    ->helperText('Harus sama dengan pemilik item. Tujuan yang berbeda akan ditolak sistem.'),
                TextInput::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->required()
                    ->helperText('Isi sebagai bilangan positif; arah stok mengikuti jenis pergerakan.'),
                TextInput::make('reference')
                    ->label('Referensi')
                    ->maxLength(255),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function itemOptions(): array
    {
        return InventoryItem::query()
            ->with(['client', 'order', 'material'])
            ->get()
            ->mapWithKeys(fn (InventoryItem $item): array => [
                $item->getKey() => sprintf(
                    '%s · %s · %s%s',
                    $item->client?->name ?? 'Tanpa klien',
                    $item->order?->order_number ?? '-',
                    $item->material?->name ?? '-',
                    $item->batch !== null ? " · {$item->batch}" : '',
                ),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function orderOptions(): array
    {
        return Order::query()
            ->with('client')
            ->get()
            ->mapWithKeys(fn (Order $order): array => [
                $order->getKey() => sprintf(
                    '%s · %s',
                    $order->order_number,
                    $order->client?->name ?? '-',
                ),
            ])
            ->all();
    }

    /**
     * Menampilkan pemilik item di bawah pilihan, agar petugas melihat batasnya
     * sebelum sempat salah memilih pesanan tujuan.
     */
    private static function ownershipHint(int|string|null $itemId): ?string
    {
        if (blank($itemId)) {
            return null;
        }

        $item = InventoryItem::query()->with(['client', 'order'])->find($itemId);

        if ($item === null) {
            return null;
        }

        return sprintf(
            'Milik %s — pesanan %s. Hanya boleh dikeluarkan untuk pesanan tersebut.',
            $item->client?->name ?? '-',
            $item->order?->order_number ?? '-',
        );
    }

    /**
     * Apakah jenis pergerakan ini menuntut pesanan tujuan (FR-007).
     *
     * State formulir dapat berupa instance enum (karena options-nya
     * MovementType::class) maupun nilai mentah dari basis data.
     */
    private static function requiresDestination(MovementType|string|int|null $type): bool
    {
        if ($type instanceof MovementType) {
            return $type->requiresOwnershipMatch();
        }

        if (blank($type)) {
            return false;
        }

        return MovementType::tryFrom((string) $type)?->requiresOwnershipMatch() ?? false;
    }
}
