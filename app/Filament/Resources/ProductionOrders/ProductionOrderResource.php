<?php

namespace App\Filament\Resources\ProductionOrders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\ProductionOrders\Pages\ListProductionOrders;
use App\Filament\Resources\ProductionOrders\Tables\ProductionOrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Antrean kerja Kepala Produksi: order yang sedang berada di lantai produksi.
 *
 * Menampilkan tiga status yang mencerminkan posisi order di lantai: Siap
 * Produksi (menunggu dimulai), Dalam Produksi (sedang dikerjakan), dan Dalam QC
 * (menunggu pemeriksaan). Permintaan klien yang belum menjadi order produksi,
 * serta order yang sudah Selesai atau Dibatalkan, tidak muncul di sini.
 *
 * Tombol "Mulai Produksi" hanya muncul pada order yang berstatus Siap Produksi
 * (lihat OrderPolicy::startProduction), sehingga satu menu melayani seluruh
 * perjalanan order di lantai tanpa perlu berpindah halaman. Menekannya membuka
 * rencana penjahit per komponen: order baru berjalan setelah setiap komponen
 * artikel punya penjahit, sehingga kolom penjahit di antrean ini selalu berisi
 * rencana yang benar-benar ditetapkan Kepala Produksi — bukan tebakan dari
 * output yang kebetulan sudah tercatat.
 *
 * Sengaja bukan bagian dari matriks Full Action: Kepala Produksi mengerjakan
 * order, bukan memeliharanya, sehingga menu Order Massal tetap milik Owner dan
 * Tim Sample.
 */
class ProductionOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'production-orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Produksi';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Antrean Produksi';

    protected static ?string $pluralModelLabel = 'Antrean Produksi';

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageProduction());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'client',
                'article.components',
                'sample',
                'componentAssignments.component',
                'componentAssignments.tailor',
            ])
            ->whereIn('status', [
                OrderStatus::READY_FOR_PRODUCTION->value,
                OrderStatus::IN_PRODUCTION->value,
                OrderStatus::IN_QC->value,
            ]);
    }

    public static function table(Table $table): Table
    {
        return ProductionOrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionOrders::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
