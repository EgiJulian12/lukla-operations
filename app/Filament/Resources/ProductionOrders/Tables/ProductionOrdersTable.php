<?php

namespace App\Filament\Resources\ProductionOrders\Tables;

use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Exceptions\IncompleteComponentAssignment;
use App\Models\Component;
use App\Models\Order;
use App\Models\OrderComponentAssignment;
use App\Models\User;
use App\Services\Production\ProductionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use InvalidArgumentException;

/**
 * Antrean order di lantai produksi untuk Kepala Produksi.
 *
 * Mencakup order yang Siap Produksi, sedang Dalam Produksi, maupun sedang
 * Dalam QC, sehingga satu menu memantau seluruh perjalanan order di lantai.
 * Tenggat terdekat diurutkan lebih dulu supaya order yang paling mendesak
 * dikerjakan lebih awal.
 */
class ProductionOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Nomor Order')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('article.name')
                    ->label('Artikel')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('sample.code')
                    ->label('Sample')
                    ->placeholder('-')
                    ->description(fn (Order $record): ?string => $record->sampleRevision !== null
                        ? 'Revisi v'.$record->sampleRevision->version
                        : null),
                TextColumn::make('work_scheme')
                    ->label('Skema')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->placeholder('-')
                    ->formatStateUsing(fn (int $state): string => "{$state} pcs")
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('componentAssignments')
                    ->label('Penjahit per Komponen')
                    ->badge()
                    ->color('gray')
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->state(fn (Order $record): array => $record->componentAssignments
                        ->map(fn (OrderComponentAssignment $assignment): string => sprintf(
                            '%s: %s',
                            $assignment->component?->name ?? 'Komponen',
                            $assignment->tailor?->name ?? 'Belum ditugaskan',
                        ))
                        ->all())
                    ->placeholder('Belum ditugaskan'),
                TextColumn::make('due_date')
                    ->label('Tenggat')
                    ->date('d/m/Y')
                    ->placeholder('Tanpa tenggat')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('work_scheme')
                    ->label('Skema Maklon')
                    ->options(WorkScheme::class),
            ])
            ->recordActions([
                self::startProductionAction(),
                self::updateAssignmentsAction(),
            ])
            ->emptyStateHeading('Tidak ada order di lantai produksi')
            ->emptyStateDescription('Order muncul di sini sejak berstatus Siap Produksi hingga selesai diperiksa QC.')
            ->defaultSort('due_date');
    }

    /**
     * Memulai produksi sekaligus menetapkan penjahit tiap komponen (FR-009).
     *
     * Formnya berisi satu isian per komponen artikel, bukan repeater bebas yang
     * bisa ditambah dan dihapus. Jumlah isian dengan begitu mengikuti artikelnya
     * sendiri, sehingga tidak ada komponen yang bisa lolos tanpa penjahit —
     * aturan yang sama juga ditegakkan service, karena modal dapat dibuka
     * sebelum komponen artikel berubah.
     */
    private static function startProductionAction(): Action
    {
        return Action::make('startProduction')
            ->label('Mulai Produksi')
            ->icon(Heroicon::OutlinedPlay)
            ->color('success')
            ->modalHeading(fn (Order $record): string => "Mulai produksi {$record->order_number}?")
            ->modalDescription('Tentukan penjahit untuk setiap komponen. Order baru berjalan setelah seluruh komponen punya penjahit.')
            ->modalSubmitActionLabel('Mulai Produksi')
            ->visible(fn (Order $record): bool => auth()->user()?->can('startProduction', $record) ?? false)
            ->form(fn (Order $record): array => self::componentPlanForm($record))
            ->action(function (Order $record, array $data): void {
                $assignments = collect($data['assignments'] ?? [])
                    ->map(fn (int|string $tailorId, int|string $componentId): array => [
                        'component_id' => (int) $componentId,
                        'user_id' => (int) $tailorId,
                    ])
                    ->values()
                    ->all();

                try {
                    app(ProductionService::class)->startProduction($record, $assignments);
                } catch (IncompleteComponentAssignment|InvalidArgumentException $exception) {
                    // Jaring pengaman untuk rencana yang tidak lengkap. Isian
                    // wajib di form sudah menutup kasus lazimnya, tetapi
                    // komponen artikel bisa berubah antara modal dibuka dan
                    // dikirim.
                    Notification::make()
                        ->title('Rencana penjahit belum lengkap')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Order masuk ke produksi')
                    ->body("{$record->order_number} berstatus Dalam Produksi dengan penjahit untuk setiap komponen.")
                    ->success()
                    ->send();
            });
    }

    /**
     * Memperbarui penugasan penjahit per komponen selama proses produksi berjalan.
     */
    private static function updateAssignmentsAction(): Action
    {
        return Action::make('updateAssignments')
            ->label('Ubah Penugasan')
            ->icon(Heroicon::OutlinedUserGroup)
            ->color('warning')
            ->modalHeading(fn (Order $record): string => "Ubah Penugasan Penjahit - {$record->order_number}")
            ->modalDescription('Sesuaikan penjahit untuk setiap komponen selama proses produksi masih berjalan.')
            ->modalSubmitActionLabel('Simpan Penugasan')
            ->visible(fn (Order $record): bool => auth()->user()?->can('updateComponentAssignments', $record) ?? false)
            ->form(fn (Order $record): array => self::componentPlanForm($record))
            ->action(function (Order $record, array $data): void {
                $assignments = collect($data['assignments'] ?? [])
                    ->map(fn (int|string $tailorId, int|string $componentId): array => [
                        'component_id' => (int) $componentId,
                        'user_id' => (int) $tailorId,
                    ])
                    ->values()
                    ->all();

                try {
                    app(ProductionService::class)->updateComponentAssignments($record, $assignments);
                } catch (IncompleteComponentAssignment|InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('Rencana penjahit belum lengkap')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Penugasan penjahit berhasil diperbarui')
                    ->body("Pemetaan komponen untuk {$record->order_number} telah disesuaikan.")
                    ->success()
                    ->send();
            });
    }

    /**
     * Satu pilihan penjahit per komponen artikel order.
     *
     * @return array<int, mixed>
     */
    private static function componentPlanForm(Order $order): array
    {
        $components = $order->components();

        if ($components->isEmpty()) {
            return [
                Placeholder::make('no_components')
                    ->label('Komponen')
                    ->content('Artikel order ini belum dipecah menjadi komponen, sehingga tidak ada penjahit yang perlu ditugaskan.'),
            ];
        }

        return [
            Section::make('Penjahit per Komponen')
                ->description('Satu komponen dipegang satu penjahit. Penjahit yang sama boleh memegang beberapa komponen.')
                ->columns(2)
                ->schema($components
                    ->map(fn (Component $component): Select => Select::make("assignments.{$component->getKey()}")
                        ->label(sprintf('%d. %s', $component->sequence, $component->name))
                        ->options(self::tailorOptions())
                        ->searchable()
                        ->default(fn (Order $record): ?int => $record->componentAssignments->firstWhere('component_id', $component->getKey())?->user_id)
                        ->required())
                    ->all()),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function tailorOptions(): array
    {
        return User::query()
            ->role(UserRole::TAILOR_OPERATOR->value)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
