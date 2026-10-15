<?php

namespace App\Filament\Portal\Resources\Invoices;

use App\Enums\InvoiceStatus;
use App\Filament\Portal\Resources\Invoices\Pages\ListPortalInvoices;
use App\Filament\Portal\Resources\Invoices\Tables\PortalInvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tagihan & Invoice milik klien yang sedang login pada panel /portal (FR-025).
 *
 * Klien dapat melihat rincian biaya yang ditagihkan oleh Finance dan melakukan
 * konfirmasi pembayaran beserta unggahan bukti transfer.
 */
class PortalInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static ?string $navigationLabel = 'Tagihan & Invoice';

    protected static ?string $modelLabel = 'Tagihan & Invoice';

    protected static ?string $pluralModelLabel = 'Tagihan & Invoice';

    protected static ?int $navigationSort = 35;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function getEloquentQuery(): Builder
    {
        // Hanya menampilkan invoice yang sudah diterbitkan Finance (bukan DRAFT)
        return parent::getEloquentQuery()
            ->with(['order', 'client'])
            ->where('status', '!=', InvoiceStatus::DRAFT->value);
    }

    public static function table(Table $table): Table
    {
        return PortalInvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortalInvoices::route('/'),
        ];
    }
}
