<?php

namespace App\Filament\Resources\Assemblies\Schemas;

use App\Enums\OutputStatus;
use App\Models\Order;
use App\Models\OutputEntry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Formulir perakitan.
 *
 * Pilihan output dibatasi pada output terverifikasi milik order yang dipilih,
 * sehingga output yang belum diverifikasi tidak dapat menyusup ke perakitan.
 */
class AssemblyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->label('Order')
                    ->options(fn (): array => Order::query()
                        ->with('client')
                        ->get()
                        ->mapWithKeys(fn (Order $order): array => [
                            $order->getKey() => sprintf('%s · %s', $order->order_number, $order->client?->name ?? '-'),
                        ])
                        ->all())
                    ->searchable()
                    ->required()
                    ->live(),
                Select::make('output_entry_ids')
                    ->label('Output Terverifikasi')
                    ->multiple()
                    ->options(fn (Get $get): array => self::verifiedOutputOptions($get('order_id')))
                    ->searchable()
                    ->required()
                    ->helperText('Pilih output yang menyusun produk ini. Hanya output terverifikasi yang tersedia.'),
                TextInput::make('quantity')
                    ->label('Jumlah Hasil Rakitan')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Pilihan output terverifikasi sebuah order: komponen · penjahit · pcs · waktu.
     *
     * Dipakai form perakitan maupun modal "Input Perakitan" pada halaman detail
     * order, supaya keduanya menawarkan pilihan yang sama persis. Menerima Order
     * sekaligus id-nya karena pemanggil dari modal sudah memegang ordernya,
     * sedangkan form hanya punya state order_id.
     *
     * @return array<int, string>
     */
    public static function verifiedOutputOptions(Order|int|string|null $order): array
    {
        $orderId = $order instanceof Order ? $order->getKey() : $order;

        if (blank($orderId)) {
            return [];
        }

        return OutputEntry::query()
            ->where('order_id', $orderId)
            ->where('status', OutputStatus::VERIFIED->value)
            ->with(['component', 'operator'])
            ->get()
            ->mapWithKeys(fn (OutputEntry $entry): array => [
                $entry->getKey() => sprintf(
                    '%s · %s · %d pcs · %s',
                    $entry->component?->name ?? '-',
                    $entry->operator?->name ?? '-',
                    $entry->quantity,
                    $entry->interval_start->format('d/m H:i'),
                ),
            ])
            ->all();
    }
}
