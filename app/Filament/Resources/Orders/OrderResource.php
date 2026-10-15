<?php

namespace App\Filament\Resources\Orders;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictsNavigationToFullAction;
use App\Filament\Portal\Resources\Orders\Schemas\PortalOrderInfolist;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Order produksi massal (FR-004).
 *
 * Order yang lahir dari konversi sample membawa revisi yang disetujui, dan
 * tautan itu ditampilkan sebagai bukti spesifikasi yang diproduksi.
 */
class OrderResource extends Resource
{
    use RestrictsNavigationToFullAction;

    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Produksi';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Pesanan';

    protected static ?string $pluralModelLabel = 'Daftar Pesanan';

    protected static ?string $navigationLabel = 'Daftar Pesanan';

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PortalOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }

    /**
     * Finance hanya boleh melihat daftar pesanan (read-only) untuk keperluan
     * penagihan dan rekonsiliasi — tanpa hak ubah atau buat order baru.
     */
    public static function canCreate(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return ! $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role === UserRole::FINANCE_PAYROLL,
        );
    }

    public static function canEdit(mixed $record): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return ! $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role === UserRole::FINANCE_PAYROLL,
        );
    }
}
