<?php

namespace App\Filament\Resources\OutputEntries;

use App\Enums\UserRole;
use App\Filament\Resources\OutputEntries\Pages\CreateOutputEntry;
use App\Filament\Resources\OutputEntries\Pages\ListOutputEntries;
use App\Filament\Resources\OutputEntries\Schemas\OutputEntryForm;
use App\Filament\Resources\OutputEntries\Tables\OutputEntriesTable;
use App\Models\OutputEntry;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Terminal output 30 menit lantai produksi (SRS view #4, FR-011, FR-012).
 *
 * Layar ini adalah tempat mandor mencatat dan memverifikasi hasil kerja.
 * Baris tidak dapat disunting setelah diverifikasi: verifikasi mengunci output
 * sebagai gerbang menuju payroll.
 */
class OutputEntryResource extends Resource
{
    protected static ?string $model = OutputEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Produksi';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Output 30 Menit';

    protected static ?string $pluralModelLabel = 'Output 30 Menit';

    public static function form(Schema $schema): Schema
    {
        return OutputEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OutputEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutputEntries::route('/'),
            'create' => CreateOutputEntry::route('/create'),
        ];
    }

    /**
     * Finance tidak mengelola output produksi; itu domain Kepala Produksi.
     * Tim Sample tidak mengelola output 30 menit.
     */
    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return ! $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role === UserRole::FINANCE_PAYROLL
                || $role === UserRole::SAMPLE_TEAM,
        );
    }
}
