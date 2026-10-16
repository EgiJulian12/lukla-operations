<?php

namespace App\Filament\Resources\OutputReviews;

use App\Enums\OrderStatus;
use App\Enums\OutputStatus;
use App\Enums\UserRole;
use App\Filament\Resources\OutputReviews\Pages\ListOutputReviews;
use App\Filament\Resources\OutputReviews\Pages\ViewOutputReview;
use App\Filament\Resources\OutputReviews\RelationManagers\OutputEntriesRelationManager;
use App\Filament\Resources\OutputReviews\Schemas\OutputReviewInfolist;
use App\Filament\Resources\OutputReviews\Tables\OutputReviewsTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Verifikasi output 30 menit per order untuk Kepala Produksi (FR-012 → FR-013).
 *
 * Output dilaporkan penjahit per komponen, tetapi yang diperiksa mandor adalah
 * pekerjaan satu order: apakah seluruh komponennya sudah dilaporkan, apakah
 * laporannya masuk akal, dan apakah produknya sudah boleh dirakit. Karena itu
 * daftar ini berbariskan order, bukan baris output — daftar datar per baris tetap
 * tersedia bagi penjahit lewat menu Output 30 Menit miliknya sendiri.
 *
 * Order yang muncul adalah order yang sudah berjalan di lantai produksi dan
 * sudah punya catatan output; order yang belum dilaporkan siapa pun belum ada
 * yang bisa diverifikasi. Halaman detailnya memuat daftar output order tersebut
 * beserta aksi verifikasi, tombol verifikasi massal, dan tombol perakitan.
 *
 * Sengaja bukan bagian dari matriks Full Action: menu ini memakai izin yang sama
 * dengan antrean produksi, yaitu wewenang Kepala Produksi.
 */
class OutputReviewResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'output-reviews';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Produksi';

    protected static ?int $navigationSort = 31;

    protected static ?string $navigationLabel = 'Output 30 Menit';

    protected static ?string $modelLabel = 'Output Produksi';

    protected static ?string $pluralModelLabel = 'Output Produksi';

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageProduction());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * Order yang sedang dikerjakan atau menunggu QC dan sudah punya catatan output.
     *
     * Jumlah output disertakan sebagai kolom agregat karena daftarnya menampilkan
     * progres verifikasi per order — tanpa itu, setiap baris akan menembak query
     * sendiri-sendiri.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['client', 'article'])
            ->withCount([
                'outputEntries',
                'assemblies',
                'outputEntries as verified_output_entries_count' => fn (Builder $query): Builder => $query
                    ->where('status', OutputStatus::VERIFIED->value),
                'outputEntries as pending_output_entries_count' => fn (Builder $query): Builder => $query
                    ->where('status', OutputStatus::SUBMITTED->value),
            ])
            ->whereHas('outputEntries')
            ->whereIn('status', [
                OrderStatus::IN_PRODUCTION->value,
                OrderStatus::IN_QC->value,
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OutputReviewInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OutputReviewsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            OutputEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutputReviews::route('/'),
            'view' => ViewOutputReview::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
