<?php

namespace App\Filament\Resources\OutputEntries\Schemas;

use App\Enums\UserRole;
use App\Models\Component;
use App\Models\Order;
use App\Models\ProductionLine;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Formulir output 30 menit.
 *
 * Kolom "Waktu Interval" menerima waktu kejadian apa pun; service membulatkan
 * ke batas interval 30 menit, sehingga petugas tidak perlu menghitung sendiri.
 *
 * Kolom "Komponen" mengikuti rencana penjahit yang ditetapkan saat produksi
 * dimulai (FR-009): begitu order punya rencana, seorang penjahit hanya
 * ditawari komponen yang memang menjadi tanggung jawabnya. Inilah cara penjahit
 * mengetahui tugasnya tanpa perlu bertanya ke mandor.
 *
 * Kolomnya dipisah dari configure() supaya halaman verifikasi order dapat
 * memakai definisi yang sama di dalam modal — mencatat output atas nama penjahit
 * tetap mungkin dari sana, tanpa kolom yang berbeda perilaku.
 */
class OutputEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::fields());
    }

    /**
     * Definisi kolom formulir output 30 menit.
     *
     * Bila ordernya sudah ditentukan oleh halaman pemanggil ($fixedOrder), kolom
     * Order ditampilkan terkunci: mencatat output ke order lain dari halaman
     * verifikasi hanya membingungkan, bukan menambah kemampuan.
     *
     * @return array<int, mixed>
     */
    public static function fields(?Order $fixedOrder = null): array
    {
        return [
            Select::make('order_id')
                ->label('Order')
                ->options(fn (): array => Order::query()
                    ->with('client')
                    ->get()
                    ->mapWithKeys(fn (Order $order): array => [
                        $order->getKey() => sprintf('%s · %s', $order->order_number, $order->client?->name ?? '-'),
                    ])
                    ->all())
                ->default($fixedOrder?->getKey())
                ->disabled($fixedOrder !== null)
                ->dehydrated()
                ->helperText($fixedOrder !== null ? 'Order mengikuti halaman ini.' : null)
                ->searchable()
                ->live()
                ->afterStateUpdated(fn ($set) => $set('component_id', null))
                ->required(),
            Select::make('production_line_id')
                ->label('Line')
                ->options(fn (): array => ProductionLine::query()
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->helperText('Opsional. Isi bila penjahit sedang bertugas di satu line; output tetap dapat dicatat tanpa line.'),
            Select::make('user_id')
                ->label('Penjahit')
                ->options(fn (): array => User::query()
                    ->role(UserRole::TAILOR_OPERATOR->value)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                // Penjahit mencatat hasil kerjanya sendiri, jadi namanya sudah
                // terisi; mandor yang mencatatkan untuk orang lain harus memilih
                // penjahitnya lebih dulu, bukan membiarkan namanya sendiri
                // tersangkut sebagai pencatat output.
                ->default(fn (): ?int => auth()->user()?->hasRoleSatisfying(
                    fn (UserRole $role): bool => $role->canSubmitOutput(),
                )
                    ? (int) auth()->id()
                    : null)
                ->searchable()
                ->live()
                ->afterStateUpdated(fn ($set) => $set('component_id', null))
                ->required(),
            Select::make('component_id')
                ->label('Komponen')
                ->placeholder(fn (Get $get): string => $get('order_id') && $get('user_id')
                    ? 'Pilih komponen yang menjadi tugas Anda...'
                    : 'Pilih order dan penjahit terlebih dahulu...')
                ->options(function (Get $get): array {
                    $order = Order::find($get('order_id'));
                    $tailor = User::find($get('user_id'));

                    if ($order === null || $tailor === null) {
                        return [];
                    }

                    // Rencana penjahit ditetapkan saat produksi dimulai,
                    // jadi penjahit hanya melihat komponennya sendiri.
                    $assigned = $order->componentsAssignedTo($tailor);

                    // Order yang berjalan sebelum rencana per komponen ada —
                    // atau artikel yang komponennya belum dipecah — belum
                    // punya penugasan apa pun; daftar komponen lengkap tetap
                    // ditawarkan supaya pencatatan output order itu tidak
                    // ikut terkunci.
                    if ($assigned->isEmpty() && ! $order->hasComponentPlan()) {
                        return Component::query()->orderBy('name')->pluck('name', 'id')->all();
                    }

                    return $assigned
                        ->mapWithKeys(fn (Component $component): array => [
                            $component->getKey() => sprintf('%s · %s', $component->code, $component->name),
                        ])
                        ->all();
                })
                ->searchable()
                ->required(),
            TextInput::make('quantity')
                ->label('Jumlah Selesai (pcs)')
                ->numeric()
                ->minValue(1)
                ->required(),
            DateTimePicker::make('interval_at')
                ->label('Waktu Interval')
                ->seconds(false)
                ->default(now())
                ->required()
                ->helperText('Dibulatkan otomatis ke interval 30 menit terdekat.'),
            Textarea::make('notes')
                ->label('Catatan')
                ->columnSpanFull(),
        ];
    }
}
