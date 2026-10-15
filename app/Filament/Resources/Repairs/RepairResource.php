<?php

namespace App\Filament\Resources\Repairs;

use App\Enums\UserRole;
use App\Filament\Resources\Repairs\Pages\ListRepairs;
use App\Filament\Resources\Repairs\Tables\RepairsTable;
use App\Models\Repair;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Terminal perbaikan barang cacat QC (FR-014, BR 4.6).
 *
 * Di sini Kepala Produksi dan QC Inspector dapat melihat barang cacat,
 * menugaskan teknisi, menandai selesai perbaikan, dan melakukan QC ulang.
 */
class RepairResource extends Resource
{
    protected static ?string $model = Repair::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Quality Control';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Perbaikan';

    protected static ?string $pluralModelLabel = 'Perbaikan (Repair)';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(UserRole::OWNER_EXECUTIVE)) {
            return false;
        }

        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canInspectQuality()
            || $role->canManageProduction()
        );
    }

    public static function table(Table $table): Table
    {
        return RepairsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRepairs::route('/'),
        ];
    }
}
