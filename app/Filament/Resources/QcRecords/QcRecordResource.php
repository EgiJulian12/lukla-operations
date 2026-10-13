<?php

namespace App\Filament\Resources\QcRecords;

use App\Enums\UserRole;
use App\Filament\Resources\QcRecords\Pages\CreateQcRecord;
use App\Filament\Resources\QcRecords\Pages\ListQcRecords;
use App\Filament\Resources\QcRecords\Schemas\QcRecordForm;
use App\Filament\Resources\QcRecords\Tables\QcRecordsTable;
use App\Models\QcRecord;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Terminal inspeksi QC (SRS view #5, FR-014).
 *
 * Hasil QC tidak dapat disunting setelah dicatat: koreksi dilakukan dengan
 * inspeksi baru, sehingga keputusan QC tetap dapat diaudit.
 */
class QcRecordResource extends Resource
{
    protected static ?string $model = QcRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'Quality Control';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Inspeksi QC';

    protected static ?string $pluralModelLabel = 'Inspeksi QC';

    public static function form(Schema $schema): Schema
    {
        return QcRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QcRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQcRecords::route('/'),
            'create' => CreateQcRecord::route('/create'),
        ];
    }

    /**
     * Finance tidak mengelola inspeksi QC; itu domain QC Inspector.
     * Tim Sample tidak mengelola inspeksi QC.
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
