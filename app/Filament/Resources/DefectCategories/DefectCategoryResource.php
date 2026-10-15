<?php

namespace App\Filament\Resources\DefectCategories;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictsNavigationToFullAction;
use App\Filament\Resources\DefectCategories\Pages\CreateDefectCategory;
use App\Filament\Resources\DefectCategories\Pages\EditDefectCategory;
use App\Filament\Resources\DefectCategories\Pages\ListDefectCategories;
use App\Filament\Resources\DefectCategories\Schemas\DefectCategoryForm;
use App\Filament\Resources\DefectCategories\Tables\DefectCategoriesTable;
use App\Models\DefectCategory;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Referensi kategori cacat (matriks RBAC, QC).
 *
 * Referensi global yang dipakai lintas klien, dirujuk setiap kali hasil QC
 * bukan lolos.
 */
class DefectCategoryResource extends Resource
{
    use RestrictsNavigationToFullAction;

    protected static ?string $model = DefectCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = 'Kategori Cacat';

    protected static ?string $pluralModelLabel = 'Kategori Cacat';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DefectCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DefectCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDefectCategories::route('/'),
            'create' => CreateDefectCategory::route('/create'),
            'edit' => EditDefectCategory::route('/{record}/edit'),
        ];
    }

    /**
     * Finance tidak mengelola kategori cacat.
     * Tim Sample juga tidak mengelola kategori cacat.
     * QC Inspector mengacu referensi kategori cacat saat inspeksi, bukan mengelola masternya.
     */
    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return ! $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role === UserRole::FINANCE_PAYROLL
                || $role === UserRole::SAMPLE_TEAM
                || $role === UserRole::QC_INSPECTOR,
        );
    }
}
