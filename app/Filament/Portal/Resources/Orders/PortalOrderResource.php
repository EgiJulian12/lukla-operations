<?php

namespace App\Filament\Portal\Resources\Orders;

use App\Filament\Portal\Resources\Orders\Pages\CreatePortalOrder;
use App\Filament\Portal\Resources\Orders\Pages\ListPortalOrders;
use App\Filament\Portal\Resources\Orders\Pages\ViewPortalOrder;
use App\Filament\Portal\Resources\Orders\Schemas\PortalOrderForm;
use App\Filament\Portal\Resources\Orders\Schemas\PortalOrderInfolist;
use App\Filament\Portal\Resources\Orders\Tables\PortalOrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Pesanan milik klien yang sedang login (FR-025).
 *
 * Klien dapat mengajukan permintaan baru dan menyetujui penawaran atas
 * pesanannya sendiri, lalu memantau progres tanpa akses ke data klien lain
 * maupun data internal pabrik.
 */
class PortalOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Pesanan Saya';

    protected static ?string $modelLabel = 'Pesanan';

    protected static ?string $pluralModelLabel = 'Pesanan Saya';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return PortalOrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PortalOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PortalOrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortalOrders::route('/'),
            'create' => CreatePortalOrder::route('/create'),
            'view' => ViewPortalOrder::route('/{record}'),
        ];
    }
}
