<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Enums\WorkScheme;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Formulir order massal.
 *
 * Order yang berasal dari konversi sample membawa nomor yang sudah dihasilkan
 * service; nomor tetap dapat disunting manual untuk order yang dibuat langsung.
 */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('order_number')
                    ->label('Nomor Order')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('work_scheme')
                    ->label('Skema Maklon')
                    ->options(WorkScheme::class)
                    ->default(WorkScheme::CMT->value)
                    ->required(),
                TextInput::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options(OrderStatus::class)
                    ->default(OrderStatus::DRAFT->value)
                    ->required(),
                DatePicker::make('due_date')
                    ->label('Tenggat'),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }
}
