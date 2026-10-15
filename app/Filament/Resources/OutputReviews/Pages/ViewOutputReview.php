<?php

namespace App\Filament\Resources\OutputReviews\Pages;

use App\Enums\OutputStatus;
use App\Exceptions\AssemblyNotReady;
use App\Exceptions\DuplicateOutputInterval;
use App\Filament\Resources\Assemblies\Schemas\AssemblyForm;
use App\Filament\Resources\OutputEntries\Schemas\OutputEntryForm;
use App\Filament\Resources\OutputReviews\OutputReviewResource;
use App\Models\Assembly;
use App\Models\Component;
use App\Models\Order;
use App\Models\OutputEntry;
use App\Models\ProductionLine;
use App\Models\User;
use App\Services\Production\ProductionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Verifikasi output satu order dan perakitan produknya (FR-012 → FR-013).
 *
 * Halaman ini adalah tempat mandor memeriksa pekerjaan satu order sampai tuntas:
 * tabel output di bawahnya memuat laporan penjahit per interval dengan aksi
 * verifikasi dan penolakan, "Verifikasi Semua" menerima seluruh laporan yang
 * masih menunggu sekaligus, dan "Input Perakitan" menyatukan output terverifikasi
 * menjadi produk utuh.
 *
 * Tombol perakitan sengaja dibiarkan terlihat meski belum bisa dipakai. Yang
 * menahannya dituliskan di keterangan modal — komponen mana yang belum
 * terverifikasi atau berapa laporan yang masih menunggu — sehingga mandor tahu
 * langkah berikutnya, bukan menghadapi tombol mati tanpa penjelasan. Aturan yang
 * sama tetap ditegakkan service, karena tombol hanyalah kenyamanan.
 *
 * Setelah perakitan, order tetap berada di lantai produksi: perakitan adalah
 * catatan peristiwa, dan sisa unit dapat dirakit pada perakitan berikutnya.
 */
class ViewOutputReview extends ViewRecord
{
    protected static string $resource = OutputReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->recordOutputAction(),
            $this->verifyAllAction(),
            $this->assemblyAction(),
        ];
    }

    /**
     * Mencatat output atas nama penjahit langsung dari halaman verifikasi.
     *
     * Mandor memang boleh mencatatkan output untuk penjahit (matriks RBAC), dan
     * jalur itu dulu ada di menu output 30 menit miliknya. Sejak daftar itu
     * berbariskan order, jalurnya dipindahkan ke sini supaya tidak hilang.
     */
    public function recordOutputAction(): Action
    {
        return Action::make('recordOutput')
            ->label('Catat Output')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(fn (): string => sprintf('Catat output order %s', $this->order()->order_number))
            ->modalWidth('lg')
            ->visible(fn (): bool => (bool) auth()->user()?->can('create', OutputEntry::class))
            ->form(OutputEntryForm::fields($this->order()))
            ->action(function (array $data): void {
                try {
                    app(ProductionService::class)->submitOutput(
                        order: $this->order(),
                        // Line boleh kosong, tetapi bila diisi harus menunjuk line
                        // yang benar-benar ada — salah isi tetap muncul sebagai
                        // galat, bukan diam-diam tercatat tanpa line.
                        line: filled($data['production_line_id'] ?? null)
                            ? ProductionLine::findOrFail($data['production_line_id'])
                            : null,
                        operator: User::findOrFail($data['user_id']),
                        component: Component::findOrFail($data['component_id']),
                        quantity: (int) $data['quantity'],
                        intervalAt: Carbon::parse($data['interval_at']),
                        notes: $data['notes'] ?? null,
                    );
                } catch (DuplicateOutputInterval $exception) {
                    Notification::make()
                        ->title('Output interval ini sudah dicatat')
                        ->body($exception->getMessage())
                        ->warning()
                        ->send();

                    return;
                }

                $this->getRecord()->refresh();

                Notification::make()
                    ->title('Output tercatat')
                    ->body(sprintf('Output order %s menunggu verifikasi.', $this->order()->order_number))
                    ->success()
                    ->send();
            });
    }

    /**
     * Memverifikasi seluruh output order yang masih menunggu sekaligus.
     *
     * Hanya ditawarkan bila memang ada yang menunggu dan pengguna berwenang
     * memverifikasi, sehingga tidak pernah ada tombol yang pasti berujung galat.
     */
    public function verifyAllAction(): Action
    {
        return Action::make('verifyAll')
            ->label('Verifikasi Semua')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn (): string => sprintf('Verifikasi seluruh output order %s?', $this->order()->order_number))
            ->modalDescription('Semua laporan yang masih menunggu keputusan ditandai terverifikasi sekaligus, sehingga bisa dihitung payroll dan dirakit.')
            ->modalSubmitActionLabel('Verifikasi Semua')
            ->visible(fn (): bool => $this->canVerifyAnyPendingOutput())
            ->action(function (): void {
                $verified = app(ProductionService::class)->verifyAllPendingOutputs($this->order(), auth()->user());

                $this->getRecord()->refresh();

                Notification::make()
                    ->title('Output order terverifikasi')
                    ->body(sprintf(
                        '%d laporan order %s telah diverifikasi.',
                        $verified,
                        $this->order()->order_number,
                    ))
                    ->success()
                    ->send();
            });
    }

    /**
     * Merakit output terverifikasi order ini menjadi produk utuh (FR-013).
     */
    public function assemblyAction(): Action
    {
        return Action::make('assembly')
            ->label('Input Perakitan')
            ->icon(Heroicon::OutlinedSquares2x2)
            ->color('primary')
            ->modalHeading(fn (): string => sprintf('Perakitan order %s', $this->order()->order_number))
            ->modalDescription(fn (): string => $this->assemblyReadinessDescription())
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Simpan Perakitan')
            ->disabled(fn (): bool => ! $this->order()->isReadyForAssembly())
            ->visible(fn (): bool => (bool) auth()->user()?->can('create', Assembly::class))
            ->form(fn (): array => $this->assemblyForm())
            ->action(function (array $data): void {
                try {
                    $assembly = app(ProductionService::class)->assemble(
                        order: $this->order(),
                        outputEntryIds: array_map(
                            fn (int|string $id): int => (int) $id,
                            $data['output_entry_ids'] ?? [],
                        ),
                        quantity: (int) $data['quantity'],
                        notes: $data['notes'] ?? null,
                    );
                } catch (AssemblyNotReady $exception) {
                    // Jaring pengaman: keadaan order dapat berubah antara modal
                    // dibuka dan dikirim, dan aturannya tetap milik service.
                    Notification::make()
                        ->title('Order belum siap dirakit')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $this->getRecord()->refresh();

                Notification::make()
                    ->title('Perakitan tercatat')
                    ->body(sprintf(
                        '%d pcs hasil rakitan order %s tercatat dan siap diperiksa QC.',
                        $assembly->quantity,
                        $this->order()->order_number,
                    ))
                    ->success()
                    ->send();
            });
    }

    /**
     * Kolom modal perakitan.
     *
     * Ordernya sudah pasti — halaman ini memang tentang satu order — sehingga yang
     * perlu dipilih hanya output mana yang menyusun produk dan berapa hasilnya.
     * Seluruh output terverifikasi dipilih lebih dulu karena itulah perakitan yang
     * paling lazim; mandor dapat mempersempitnya untuk perakitan bertahap.
     *
     * @return array<int, mixed>
     */
    private function assemblyForm(): array
    {
        $order = $this->order();

        return [
            Select::make('output_entry_ids')
                ->label('Output Terverifikasi')
                ->multiple()
                ->options(AssemblyForm::verifiedOutputOptions($order))
                ->default($order->outputEntries()
                    ->where('status', OutputStatus::VERIFIED->value)
                    ->pluck('id')
                    ->all())
                ->searchable()
                ->required()
                ->helperText('Pilih output yang menyusun produk ini. Hanya output terverifikasi yang tersedia.'),
            TextInput::make('quantity')
                ->label('Jumlah Hasil Rakitan')
                ->numeric()
                ->minValue(1)
                ->required(),
            Textarea::make('notes')
                ->label('Catatan')
                ->columnSpanFull(),
        ];
    }

    /**
     * Order yang sedang dibuka.
     *
     * Resource ini hanya pernah melayani model Order, jadi penyempitan tipe di
     * sini menghindarkan pemeriksaan null berulang di setiap aksi.
     */
    private function order(): Order
    {
        /** @var Order $order */
        $order = $this->getRecord();

        return $order;
    }

    /**
     * Apakah pengguna berwenang memverifikasi setidaknya satu output order ini.
     *
     * Wewenangnya ditanyakan per baris lewat policy, sehingga aturan separasi tugas
     * (FR-012) tidak didefinisikan ulang di halaman ini.
     */
    private function canVerifyAnyPendingOutput(): bool
    {
        return $this->order()->outputEntries()
            ->where('status', OutputStatus::SUBMITTED->value)
            ->get()
            ->contains(fn (OutputEntry $entry): bool => (bool) auth()->user()?->can('verify', $entry));
    }

    /**
     * Keterangan modal perakitan: apa yang masih menahan tombolnya, atau bahwa
     * perakitan boleh dicatat bertahap.
     *
     * Yang dilaporkan adalah langkah berikutnya lebih dulu — verifikasi — karena
     * itu yang bisa dikerjakan dari halaman ini sekarang; komponen yang belum
     * dilaporkan penjahitnya baru terlihat setelah verifikasi itu selesai.
     */
    private function assemblyReadinessDescription(): string
    {
        $order = $this->order();
        $pending = $order->pendingOutputVerificationCount();

        if ($pending > 0) {
            return sprintf(
                'Belum dapat dirakit. Masih ada %d output yang menunggu verifikasi — verifikasi dulu, lalu periksa kembali komponen yang belum selesai.',
                $pending,
            );
        }

        $missing = $order->componentsWithoutVerifiedOutput();

        if ($missing->isNotEmpty()) {
            return sprintf(
                'Belum dapat dirakit. Komponen berikut belum punya output terverifikasi: %s.',
                $missing->pluck('name')->implode(', '),
            );
        }

        if (! $order->isReadyForAssembly()) {
            return 'Belum dapat dirakit. Order ini belum punya satu pun output terverifikasi.';
        }

        return 'Hanya output terverifikasi yang dapat dirakit. Perakitan boleh dicatat lebih dari sekali untuk hasil bertahap.';
    }
}
