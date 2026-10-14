<?php

namespace App\Filament\Resources\QcRecords\Schemas;

use App\Enums\QcResult;
use App\Models\Assembly;
use App\Models\DefectCategory;
use App\Models\Order;
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
                Select::make('order_id')
                    ->label('Filter Berdasarkan Order')
                    ->placeholder('Pilih order terlebih dahulu...')
                    ->options(fn (): array => Order::query()
                        ->whereHas('assemblies', fn ($q) => $q->pendingQc())
                        ->latest()
                        ->pluck('order_number', 'id')
                        ->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('assembly_id', null))
                    ->dehydrated(false),
                Select::make('assembly_id')
                    ->label('Perakitan yang Siap Diperiksa')
                    ->placeholder('Pilih perakitan...')
                    ->options(function (Get $get): array {
                        $orderId = $get('order_id');
                        $query = Assembly::query()
                            ->with('order')
                            ->pendingQc()
                            ->latest('assembled_at');

                        if ($orderId) {
                            $query->where('order_id', $orderId);
                        }

                        return $query->limit(200)
                            ->get()
                            ->mapWithKeys(fn (Assembly $assembly): array => [
                                $assembly->getKey() => sprintf(
                                    '%s · %d unit siap cek (total: %d) · %s',
                                    $assembly->order?->order_number ?? '-',
                                    $assembly->remainingQuantity(),
                                    $assembly->quantity,
                                    $assembly->assembled_at->format('d/m H:i'),
                                ),
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($set, $state) {
                        if ($state && ($assembly = Assembly::find($state))) {
                            $set('quantity', $assembly->remainingQuantity());
                        }
                    }),
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
