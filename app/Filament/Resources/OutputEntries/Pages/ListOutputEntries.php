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
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
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
 * dengan tombol "Kerjakan" di tiap baris untuk mencatat output 30 menit.
 * Daftar ini sekaligus penanda tugas mana yang masih terbuka: satu komponen
 * tetap bisa dikerjakan — dan tetap boleh dilaporkan berulang per interval —
 * selama order-nya belum mencapai target QC, yaitu saat jumlah unit yang lolos
 * QC sudah mencukupi jumlah yang dipesan. Lihat
 * OrderComponentAssignment::hasReachedQcTarget().
 *
 * Untuk mandor / kepala produksi: menampilkan tabel output seperti biasa
 * dengan aksi verifikasi dan penolakan.
 */
class ListOutputEntries extends ListRecords
{
    protected static string $resource = OutputEntryResource::class;

    /**
     * Ekspresi JavaScript yang memunculkan field hanya saat stopwatch
     * dihentikan.
     *
     * Ditulis sebagai JavaScript, bukan PHP: `$get()` di sini adalah utilitas
     * sisi peramban yang membaca state Livewire secara reaktif, jadi perubahan
     * penanda dari stopwatch langsung terlihat tanpa permintaan ke server.
     * Jalurnya relatif terhadap container field — sama-sama "Data Output" —
     * sehingga tidak bergantung pada indeks modal yang sedang terbuka.
     */
    private const VISIBLE_WHEN_STOPWATCH_STOPPED_JS = <<<'JS'
        $get('work_timer_stopped')
        JS;

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
                    // Order yang sudah di QC tetap ditampilkan: justru di sana
                    // jumlah unit yang lolos QC bertambah, dan itulah patokan
                    // tugas ini ditutup. Order yang sudah Selesai/Ditutup tidak
                    // ikut, karena target QC-nya pasti sudah tercapai.
                    ->whereHas('order', fn (Builder $q) => $q->whereIn('status', [
                        OrderStatus::READY_FOR_PRODUCTION->value,
                        OrderStatus::IN_PRODUCTION->value,
                        OrderStatus::IN_QC->value,
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
                    ->label('Kerjakan')
                    ->icon(Heroicon::OutlinedPlayCircle)
                    ->color('primary')
                    // Tugas tertutup saat target QC order-nya tercapai, bukan
                    // saat komponennya dilaporkan: penjahit boleh melaporkan
                    // interval berikutnya selama unit yang lolos QC belum
                    // mencukupi jumlah yang dipesan — lihat
                    // hasReachedQcTarget().
                    ->visible(fn (OrderComponentAssignment $record): bool => ! $record->hasReachedQcTarget())
                    ->modalHeading(fn (OrderComponentAssignment $record): string => sprintf(
                        'Kerjakan Output — %s · %s',
                        $record->component?->name ?? '-',
                        $record->order?->order_number ?? '-',
                    ))
                    ->modalDescription(fn (OrderComponentAssignment $record): string => $record->latestOutput()?->status === OutputStatus::REJECTED
                        ? sprintf(
                            'Laporan sebelumnya ditolak: %s. Kerjakan ulang hasil perbaikan Anda.',
                            $record->latestOutput()?->notes ?? 'tanpa alasan tertulis',
                        )
                        : 'Jalankan stopwatch saat pengerjaan dimulai, lalu isi jumlah komponen yang selesai.')
                    ->modalWidth('md')
                    // Modal ini tidak boleh tertutup sendiri. Di lantai
                    // produksi backdrop dan tombol Esc gampang tersenggol,
                    // dan laporan yang tertutup tanpa dikirim membuang waktu
                    // pengerjaan yang sudah jalan. Karena itu ketiga jalan
                    // keluar diam-diam ditutup: klik di luar, Esc, dan tombol
                    // silang. Pintu keluarnya tinggal "Kirim laporan" dan
                    // "Batal" — dan yang terakhir pun bertanya dulu.
                    ->closeModalByClickingAway(false)
                    ->closeModalByEscaping(false)
                    ->modalCloseButton(false)
                    ->modalCancelAction(function (Action $action): Action {
                        // Membatalkan pengerjaan membuang waktu yang sudah
                        // dicatat, jadi Batal pun minta konfirmasi. Tombol
                        // konfirmasinya adalah modal anak dari modal ini, dan
                        // anak hanya menutup modalnya sendiri — lihat
                        // InteractsWithActions::unmountAction(). Tanpa
                        // cancelParentActions(), modal pengerjaan cuma tertutup
                        // sementara lalu terbuka lagi begitu konfirmasi
                        // dijawab, jadi keduanya diikat untuk tutup bersama.
                        return $action
                            ->label('Batal')
                            ->requiresConfirmation()
                            ->overlayParentActions()
                            ->cancelParentActions()
                            ->modalHeading('Batalkan pengerjaan ini?')
                            ->modalDescription('Waktu pengerjaan yang sudah berjalan akan dibuang dan laporan tidak jadi dikirim.')
                            ->modalSubmitActionLabel('Ya, batalkan')
                            ->modalCancelActionLabel('Lanjut mengerjakan');
                    })
                    ->modalSubmitActionLabel('Kirim laporan')
                    ->modalSubmitAction(function (Action $action, array $mountedActions): Action {
                        // Tombol kirim baru terbuka setelah tombol "Stop"
                        // ditekan: hanya saat itulah penanda work_timer_stopped
                        // terisi, dan hanya saat itu pula field Jumlah Selesai
                        // dan Catatan muncul. Batasnya dipasang di peramban,
                        // bukan di server, supaya tombolnya langsung terbuka
                        // tanpa perlu bolak-balik ke server — dan state itulah
                        // yang dinilai saat laporan dikirim.
                        //
                        // Path state dikirim lewat atribut data, bukan ditulis
                        // sebagai literal di dalam x-effect: kutip di dalam
                        // atribut HTML berisiko di-escape menjadi entitas, dan
                        // ekspresi Alpine yang tidak berkutip sama sekali tidak
                        // bergantung pada hasil escape itu.
                        $stoppedPath = sprintf(
                            'mountedActions.%s.data.work_timer_stopped',
                            array_key_last($mountedActions) ?? 0,
                        );

                        return $action->extraAttributes([
                            'data-work-timer-stopped-path' => $stoppedPath,
                            'x-effect' => '$el.disabled = ! $wire.get($el.dataset.workTimerStoppedPath)',
                        ]);
                    })
                    ->form(function (OrderComponentAssignment $record): array {
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
                                    ViewField::make('work_timer')
                                        ->view('filament.forms.components.output-work-timer')
                                        ->columnSpanFull(),
                                    // Penanda keadaan stopwatch: terisi hanya
                                    // selama pengerjaan dihentikan. Ditulis
                                    // stopwatch di peramban, dan dibaca field
                                    // Jumlah Selesai, Catatan, serta tombol
                                    // kirim modal. Tidak ikut dikirim ke
                                    // layanan produksi — hanya mengatur
                                    // tampilan.
                                    Hidden::make('work_timer_stopped')
                                        ->dehydrated(false),
                                    // Waktu Mulai hanya terisi stopwatch, bukan
                                    // diketik: distempel saat "Mulai" ditekan dan
                                    // mengikuti pencacah sampai "Stop" atau
                                    // laporan dikirim.
                                    DateTimePicker::make('interval_start')
                                        ->label('Waktu Mulai')
                                        ->seconds(true)
                                        ->readOnly()
                                        ->dehydrated()
                                        ->helperText('Terisi otomatis saat stopwatch dimulai.'),
                                    // Hasil pengerjaan baru bisa dilaporkan setelah
                                    // pengerjaannya dihentikan, jadi jumlahnya pun
                                    // hanya ditanyakan saat itu.
                                    TextInput::make('quantity')
                                        ->label('Jumlah Selesai (pcs)')
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(100)
                                        ->required()
                                        ->visibleJs(self::VISIBLE_WHEN_STOPWATCH_STOPPED_JS),
                                    Textarea::make('notes')
                                        ->label('Catatan')
                                        ->columnSpanFull()
                                        ->visibleJs(self::VISIBLE_WHEN_STOPWATCH_STOPPED_JS),
                                    // Waktu Selesai tidak ditampilkan: penjahit
                                    // mengikuti pencacah stopwatch, bukan jam.
                                    // Nilainya tetap ikut terkirim.
                                    Hidden::make('interval_end'),
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
                        $intervalStart = filled($data['interval_start'] ?? null)
                            ? Carbon::parse($data['interval_start'])
                            : null;

                        // Stopwatch yang belum dihentikan bukan alasan untuk
                        // menolak laporan: waktu kirim dipakai sebagai akhir
                        // pengerjaan, sama seperti menekan "Stop" saat itu.
                        // Tanpa waktu mulai sama sekali — mis. peramban tanpa
                        // JavaScript, sehingga tombol kirim tidak terkunci —
                        // laporan jatuh ke jalur lama dan dibulatkan ke interval
                        // 30 menit, bukan berakhir sebelum dimulai.
                        $intervalEnd = $intervalStart === null
                            ? null
                            : (filled($data['interval_end'] ?? null)
                                ? Carbon::parse($data['interval_end'])
                                : Carbon::now());

                        try {
                            $entry = app(ProductionService::class)->submitOutput(
                                order: $order,
                                line: $activeAssignment?->productionLine,
                                operator: $tailor,
                                component: $component,
                                quantity: (int) $data['quantity'],
                                intervalAt: $intervalStart ?? Carbon::now(),
                                intervalEnd: $intervalEnd,
                                notes: $data['notes'] ?? null,
                            );

                            Notification::make()
                                ->title('Pengerjaan berhasil dicatat')
                                ->body(sprintf(
                                    '%d pcs komponen %s tercatat untuk pengerjaan %s — %s.',
                                    $entry->quantity,
                                    $component->name,
                                    $entry->interval_start->format('H:i'),
                                    $entry->interval_end->format('H:i'),
                                ))
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
