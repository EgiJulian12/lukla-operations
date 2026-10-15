<?php

namespace App\Filament\Resources\OutputEntries\Pages;

use App\Enums\OrderStatus;
use App\Enums\OutputStatus;
use App\Enums\UserRole;
use App\Exceptions\DuplicateOutputInterval;
use App\Filament\Resources\OutputEntries\OutputEntryResource;
use App\Models\Component;
use App\Models\LineAssignment;
use App\Models\Order;
use App\Models\OrderComponentAssignment;
use App\Models\User;
use App\Services\Production\ProductionService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Halaman daftar output 30 menit.
 *
 * Untuk penjahit: menampilkan daftar komponen yang ditugaskan ke mereka
 * dengan tombol "Laporkan" di tiap baris untuk mencatat output 30 menit.
 * Daftar ini sekaligus penanda tugas mana yang masih terbuka: komponen yang
 * sudah dilaporkan kehilangan tombolnya, dan mendapatkannya kembali begitu
 * laporannya ditolak mandor.
 *
 * Untuk mandor / kepala produksi: menampilkan tabel output seperti biasa
 * dengan aksi verifikasi dan penolakan.
 */
class ListOutputEntries extends ListRecords
{
    protected static string $resource = OutputEntryResource::class;

    protected function getHeaderActions(): array
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->hasRoleSatisfying(fn (UserRole $role): bool => $role === UserRole::TAILOR_OPERATOR)) {
            return [];
        }

        return [
            CreateAction::make()->label('Catat Output'),
        ];
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->hasRoleSatisfying(fn (UserRole $role): bool => $role === UserRole::TAILOR_OPERATOR)) {
            return $this->tailorTable($table->query(
                OrderComponentAssignment::query()
                    ->where('user_id', $user->getKey())
                    ->with(['order.client', 'component'])
                    ->whereHas('order', fn (Builder $q) => $q->whereIn('status', [
                        OrderStatus::READY_FOR_PRODUCTION->value,
                        OrderStatus::IN_PRODUCTION->value,
                    ]))
            ));
        }

        return parent::table($table);
    }

    /**
     * Tabel komponen yang ditugaskan ke penjahit yang sedang login.
     * Query di-inject dari table() sehingga tidak ada filter resource yang ikut terpasang.
     *
     * Filament memanggil table() halaman ini SETELAH konfigurasi resource
     * terpasang pada instance tabel yang sama (InteractsWithTable::getTable()),
     * jadi tabel ini mewarisi apa pun yang dipasang OutputEntriesTable — termasuk
     * filter relasi `productionLine` yang tidak ada pada model penugasan dan
     * akan melempar RelationNotFoundException begitu filter itu dibangun.
     * Karena itu sisa konfigurasi model output dibuang lebih dulu.
     */
    private function tailorTable(Table $table): Table
    {
        return $table
            ->filters([])
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->description(fn (OrderComponentAssignment $record): string => $record->order?->client?->name ?? '-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('component.name')
                    ->label('Komponen')
                    ->description(fn (OrderComponentAssignment $record): string => $record->component?->code ?? '-')
                    ->searchable(),
                TextColumn::make('assigned_at')
                    ->label('Ditugaskan')
                    ->since()
                    ->sortable(),
                TextColumn::make('laporan')
                    ->label('Laporan')
                    ->badge()
                    ->state(fn (OrderComponentAssignment $record): ?OutputStatus => $record->latestOutput()?->status)
                    ->placeholder('Belum dilaporkan')
                    ->description(fn (OrderComponentAssignment $record): ?string => $record->latestOutput()?->status === OutputStatus::REJECTED
                        ? sprintf('Alasan: %s', $record->latestOutput()?->notes ?? '-')
                        : null),
            ])
            ->recordActions([
                Action::make('report')
                    ->label('Laporkan')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('primary')
                    // Tugas tertutup begitu dilaporkan, dan terbuka lagi kalau
                    // laporannya ditolak — lihat isReported().
                    ->visible(fn (OrderComponentAssignment $record): bool => ! $record->isReported())
                    ->modalHeading(fn (OrderComponentAssignment $record): string => sprintf(
                        'Laporan Output — %s · %s',
                        $record->component?->name ?? '-',
                        $record->order?->order_number ?? '-',
                    ))
                    ->modalDescription(fn (OrderComponentAssignment $record): string => $record->latestOutput()?->status === OutputStatus::REJECTED
                        ? sprintf(
                            'Laporan sebelumnya ditolak: %s. Laporkan ulang hasil kerja Anda.',
                            $record->latestOutput()?->notes ?? 'tanpa alasan tertulis',
                        )
                        : 'Isi jumlah komponen yang selesai Anda kerjakan pada interval berjalan.')
                    ->modalWidth('md')
                    ->form(function (OrderComponentAssignment $record): array {
                        $now = Carbon::now();

                        return [
                            Section::make('Detail Tugas')
                                ->columns(2)
                                ->schema([
                                    Placeholder::make('order_info')
                                        ->label('Order')
                                        ->content(sprintf(
                                            '%s · %s',
                                            $record->order?->order_number ?? '-',
                                            $record->order?->client?->name ?? '-',
                                        )),
                                    Placeholder::make('component_info')
                                        ->label('Komponen')
                                        ->content(sprintf(
                                            '%s · %s',
                                            $record->component?->code ?? '-',
                                            $record->component?->name ?? '-',
                                        )),
                                ]),

                            Section::make('Data Output')
                                ->schema([
                                    TextInput::make('quantity')
                                        ->label('Jumlah Selesai (pcs)')
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(100)
                                        ->required(),
                                    DateTimePicker::make('interval_at')
                                        ->label('Waktu Interval')
                                        ->seconds(false)
                                        ->default($now)
                                        ->disabled()
                                        ->dehydrated()
                                        ->helperText('Waktu dibulatkan otomatis ke interval 30 menit.'),
                                    Textarea::make('notes')
                                        ->label('Catatan')
                                        ->columnSpanFull(),
                                ]),
                        ];
                    })
                    ->action(function (OrderComponentAssignment $record, array $data): void {
                        /** @var User $tailor */
                        $tailor = auth()->user();

                        // Line hanya dilampirkan bila ada. Penugasan penjahit
                        // ditetapkan per komponen, sehingga penjahit yang tidak
                        // sedang terdaftar di line mana pun tetap boleh
                        // melaporkan hasil kerjanya. Bila ada, line aktif ikut
                        // dicatat untuk menelusuri output per line.
                        $activeAssignment = LineAssignment::query()
                            ->where('user_id', $tailor->getKey())
                            ->whereNull('unassigned_at')
                            ->with('productionLine')
                            ->latest('assigned_at')
                            ->first();

                        $order = Order::findOrFail($record->order_id);
                        $component = Component::findOrFail($record->component_id);
                        $intervalAt = Carbon::parse($data['interval_at']);

                        try {
                            app(ProductionService::class)->submitOutput(
                                order: $order,
                                line: $activeAssignment?->productionLine,
                                operator: $tailor,
                                component: $component,
                                quantity: (int) $data['quantity'],
                                intervalAt: $intervalAt,
                                notes: $data['notes'] ?? null,
                            );

                            Notification::make()
                                ->title('Output berhasil dilaporkan')
                                ->body(sprintf('%d pcs komponen %s telah dicatat.', $data['quantity'], $component->name))
                                ->success()
                                ->send();
                        } catch (DuplicateOutputInterval $e) {
                            Notification::make()
                                ->title('Output interval ini sudah dicatat')
                                ->body($e->getMessage())
                                ->warning()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('assigned_at', 'desc')
            ->emptyStateHeading('Belum ada komponen yang ditugaskan')
            ->emptyStateDescription('Kepala Produksi akan menugaskan komponen saat order masuk ke tahap produksi.');
    }
}
