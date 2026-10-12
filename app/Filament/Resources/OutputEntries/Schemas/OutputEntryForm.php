<?php

namespace App\Filament\Resources\OutputEntries\Schemas;

use App\Enums\UserRole;
use App\Models\Component;
use App\Models\Order;
use App\Models\ProductionLine;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Formulir output 30 menit.
 *
 * Kolom "Waktu Interval" menerima waktu kejadian apa pun; service membulatkan
 * ke batas interval 30 menit, sehingga petugas tidak perlu menghitung sendiri.
 */
class OutputEntryForm
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
                    ->required(),
                Select::make('production_line_id')
                    ->label('Line')
                    ->options(fn (): array => ProductionLine::query()
                        ->where('is_active', true)
                        ->orderBy('code')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
                Select::make('user_id')
                    ->label('Penjahit')
                    ->options(fn (): array => User::query()
                        ->role(UserRole::TAILOR_OPERATOR->value)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->default(fn (): ?int => auth()->id())
                    ->searchable()
                    ->required(),
                Select::make('component_id')
                    ->label('Komponen')
                    ->options(fn (): array => Component::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                TextInput::make('quantity')
                    ->label('Jumlah Selesai (pcs)')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                DateTimePicker::make('interval_at')
                    ->label('Waktu Interval')
                    ->seconds(false)
                    ->default(now())
                    ->required()
                    ->helperText('Dibulatkan otomatis ke interval 30 menit terdekat.'),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }
}
