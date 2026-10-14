<?php

namespace App\Filament\Resources\PurchaseOrders;

use App\Filament\Concerns\RestrictsNavigationToFullAction;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Filament\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Models\PurchaseOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * PO bahan — pesanan pembelian ke pemasok (matriks RBAC SRS, Finance).
 *
 * Persetujuan dijalankan lewat PurchaseOrderService dari daftar PO, bukan dari
 * formulir, sehingga urutan buat lalu setujui tidak dapat dilompati.
 */
class PurchaseOrderResource extends Resource
{
    use RestrictsNavigationToFullAction;

    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Payroll & Biaya';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'PO Bahan';

    protected static ?string $pluralModelLabel = 'PO Bahan';

    protected static ?string $recordTitleAttribute = 'po_number';

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
