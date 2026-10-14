<?php

namespace App\Filament\Resources\Components;

use App\Enums\UserRole;
use App\Filament\Resources\Components\Pages\CreateComponent;
use App\Filament\Resources\Components\Pages\EditComponent;
use App\Filament\Resources\Components\Pages\ListComponents;
use App\Filament\Resources\Components\Schemas\ComponentForm;
use App\Filament\Resources\Components\Tables\ComponentsTable;
use App\Models\Component;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Data induk komponen (matriks RBAC, Tim Sample).
 *
 * Komponen adalah bagian artikel yang dijahit terpisah lalu dirakit, dan
 * menjadi satuan untuk time study, output 30 menit, serta rate card.
 */
class ComponentResource extends Resource
{
    protected static ?string $model = Component::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Komponen';

    protected static ?string $pluralModelLabel = 'Komponen';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ComponentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComponentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComponents::route('/'),
            'create' => CreateComponent::route('/create'),
            'edit' => EditComponent::route('/{record}/edit'),
        ];
    }

    /**
     * Finance, Owner, dan Production Lead dapat melihat komponen untuk invoice dan rate.
     * Warehouse, Penjahit, dan Client tidak memerlukan akses Master Data komponen.
     */
    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return ! $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role === UserRole::WAREHOUSE
                || $role === UserRole::TAILOR_OPERATOR
                || $role === UserRole::CLIENT_MAKLON,
        );
    }
}
