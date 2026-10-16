<?php

namespace App\Filament\Resources\OverheadAllocations;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictsNavigationToFullAction;
use App\Filament\Resources\OverheadAllocations\Pages\ListOverheadAllocations;
use App\Filament\Resources\OverheadAllocations\Tables\OverheadAllocationsTable;
use App\Models\OverheadAllocation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Laporan overhead per unit bulanan (FR-018).
 *
 * Hanya-baca: baris dibuat oleh perhitungan dari halaman Biaya Overhead.
 */
class OverheadAllocationResource extends Resource
{
    use RestrictsNavigationToFullAction;

    protected static ?string $model = OverheadAllocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Payroll & Biaya';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'Overhead per Unit';

    protected static ?string $pluralModelLabel = 'Overhead per Unit';

    public static function table(Table $table): Table
    {
        return OverheadAllocationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOverheadAllocations::route('/'),
        ];
    }

    /**
     * Tim Sample tidak mengelola overhead; itu domain Finance.
     */
    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return ! $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role === UserRole::SAMPLE_TEAM
                || $role === UserRole::OWNER_EXECUTIVE,
        );
    }
}
