<?php

namespace App\Filament\Resources\RateCards;

use App\Filament\Concerns\RestrictsNavigationToFullAction;
use App\Filament\Resources\RateCards\Pages\CreateRateCard;
use App\Filament\Resources\RateCards\Pages\EditRateCard;
use App\Filament\Resources\RateCards\Pages\ListRateCards;
use App\Filament\Resources\RateCards\Schemas\RateCardForm;
use App\Filament\Resources\RateCards\Tables\RateCardsTable;
use App\Models\RateCard;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Rate card ongkos potong/jahit (matriks RBAC, Finance dan Owner).
 *
 * Tarif lama tidak dihapus, melainkan ditutup dengan effective_to, sehingga
 * payroll periode lalu tetap dapat dihitung ulang dengan tarif yang berlaku
 * saat itu (FR-010).
 */
class RateCardResource extends Resource
{
    use RestrictsNavigationToFullAction;

    protected static ?string $model = RateCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Payroll & Biaya';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Rate Card';

    protected static ?string $pluralModelLabel = 'Rate Card';

    public static function form(Schema $schema): Schema
    {
        return RateCardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RateCardsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRateCards::route('/'),
            'create' => CreateRateCard::route('/create'),
            'edit' => EditRateCard::route('/{record}/edit'),
        ];
    }
}
