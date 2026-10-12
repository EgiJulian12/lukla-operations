<?php

namespace App\Filament\Resources\QcRecords\Schemas;

use App\Enums\QcResult;
use App\Models\Assembly;
use App\Models\DefectCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Formulir inspeksi QC.
 *
 * Kategori cacat hanya muncul, dan baru wajib, ketika hasilnya bukan lolos
 * (FR-014). Klien dan order tidak diinput: keduanya mengikuti perakitan.
 */
class QcRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('assembly_id')
                    ->label('Perakitan')
                    ->options(fn (): array => Assembly::query()
                        ->with('order')
                        ->latest('assembled_at')
                        ->limit(200)
                        ->get()
                        ->mapWithKeys(fn (Assembly $assembly): array => [
                            $assembly->getKey() => sprintf(
                                '%s · %d unit · %s',
                                $assembly->order?->order_number ?? '-',
                                $assembly->quantity,
                                $assembly->assembled_at->format('d/m H:i'),
                            ),
                        ])
                        ->all())
                    ->searchable()
                    ->required(),
                Select::make('result')
                    ->label('Hasil')
                    ->options(QcResult::class)
                    ->default(QcResult::PASS->value)
                    ->required()
                    ->live(),
                Select::make('defect_category_id')
                    ->label('Kategori Cacat')
                    ->options(fn (): array => DefectCategory::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->visible(fn (Get $get): bool => self::requiresDefectCategory($get('result')))
                    ->required(fn (Get $get): bool => self::requiresDefectCategory($get('result'))),
                TextInput::make('quantity')
                    ->label('Jumlah Diperiksa')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * State formulir dapat berupa instance enum (karena options-nya
     * QcResult::class) maupun nilai mentah dari basis data.
     */
    private static function requiresDefectCategory(QcResult|string|int|null $result): bool
    {
        if ($result instanceof QcResult) {
            return $result->requiresDefectCategory();
        }

        if (blank($result)) {
            return false;
        }

        return QcResult::tryFrom((string) $result)?->requiresDefectCategory() ?? false;
    }
}
