<?php

namespace App\Filament\Resources\MyPurchaseOrders;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictsNavigationToFullAction;
use App\Filament\Resources\MyPurchaseOrders\Pages\ListMyPurchaseOrders;
use App\Filament\Resources\MyPurchaseOrders\Tables\MyPurchaseOrdersTable;
use App\Models\PurchaseOrder;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Menu Sidebar untuk melihat riwayat dan status pengajuan PO khusus milik pengguna yang sedang login.
 *
 * Menu ini berjalan bersama tiga peran yang sama dengan PurchaseOrderPolicy:
 * gudang mengajukan PO-nya sendiri, Finance dan Owner meninjau seluruhnya.
 * Karena itu pendaftaran sidebar-nya mengikuti matriks RoleMenuAccess —
 * sebelumnya menu ini muncul di sidebar semua peran hanya karena tidak ikut
 * matriks. canAccess() di bawah tetap membuka halamannya lewat URL bagi siapa
 * pun yang sudah login.
 */
class MyPurchaseOrderResource extends Resource
{
    use RestrictsNavigationToFullAction;

    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Gudang & Cutting';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Pengajuan PO Gudang';

    protected static ?string $pluralModelLabel = 'Pengajuan PO Gudang';

    protected static ?string $slug = 'my-purchase-orders';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user !== null && $user->hasRole(UserRole::WAREHOUSE);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['order', 'client', 'items.material', 'approver', 'requester']);

        $user = auth()->user();

        // Finance dan Owner dapat melihat seluruh pengajuan PO dari gudang
        if ($user && ($user->hasRole(UserRole::FINANCE_PAYROLL) || $user->hasRole(UserRole::OWNER_EXECUTIVE))) {
            return $query;
        }

        // Petugas gudang hanya melihat pengajuan miliknya sendiri
        return $query->where('requested_by', auth()->id());
    }

    public static function table(Table $table): Table
    {
        return MyPurchaseOrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyPurchaseOrders::route('/'),
        ];
    }
}
