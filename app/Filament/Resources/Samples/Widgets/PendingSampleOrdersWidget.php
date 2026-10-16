<?php

namespace App\Filament\Resources\Samples\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Samples\SampleResource;
use App\Models\Article;
use App\Models\Order;
use App\Services\Sample\SampleService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Str;

/**
 * Antrean pesanan massal yang telah disetujui penawarannya oleh klien
 * dan menunggu Tim Sample menentukan artikel serta memulai pembuatan sample v1.
 */
class PendingSampleOrdersWidget extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Antrean Pesanan Menunggu Pembuatan Sample')
            ->description('Pesanan yang telah disetujui klien dan siap ditentukan artikelnya untuk pembuatan sample.')
            ->query(
                Order::query()
                    ->withoutGlobalScope('client')
                    ->where('status', OrderStatus::QUOTE_APPROVED->value)
                    ->whereNotNull('sample_requested_at')
                    ->whereNull('sample_id')
                    ->with('client')
                    ->latest('sample_requested_at')
            )
            ->columns([
                TextColumn::make('order_number')
                    ->label('Nomor Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('work_scheme')
                    ->label('Skema Maklon')
                    ->badge(),
                TextColumn::make('description')
                    ->label('Deskripsi Desain Klien')
                    ->limit(50)
                    ->placeholder('-'),
                TextColumn::make('start_date')
                    ->label('Mulai Project')
                    ->date()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('quote_approved_at')
                    ->label('Disetujui Klien')
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('createSample')
                    ->label('Tentukan Artikel & Mulai Sample')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->color('primary')
                    ->modalHeading('Tentukan Artikel & Inisialisasi Sample v1')
                    ->modalDescription('Pilih apakah pesanan ini menggunakan master artikel yang sudah ada milik klien, atau buat master artikel baru.')
                    ->schema([
                        Radio::make('article_mode')
                            ->label('Pilihan Artikel')
                            ->options([
                                'existing' => 'Pilih Artikel yang Sudah Ada',
                                'new' => 'Buat Artikel Baru',
                            ])
                            ->default(fn (Order $record): string => Article::where('client_id', $record->client_id)->exists() ? 'existing' : 'new')
                            ->live()
                            ->required(),
                        Select::make('article_id')
                            ->label('Pilih Artikel Klien')
                            ->placeholder('Pilih artikel...')
                            ->options(fn (Order $record): array => Article::where('client_id', $record->client_id)->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('article_mode') === 'existing')
                            ->required(fn (Get $get): bool => $get('article_mode') === 'existing')
                            ->helperText('Gunakan master artikel yang sudah terdaftar untuk klien ini.'),
                        TextInput::make('name')
                            ->label('Nama Artikel Baru')
                            ->default(fn (Order $record): string => Str::limit(trim((string) $record->description), 80, '') ?: 'Artikel '.$record->order_number)
                            ->visible(fn (Get $get): bool => $get('article_mode') === 'new')
                            ->required(fn (Get $get): bool => $get('article_mode') === 'new'),
                        TextInput::make('code')
                            ->label('Kode / SKU Artikel')
                            ->default(fn (Order $record): string => $record->order_number)
                            ->visible(fn (Get $get): bool => $get('article_mode') === 'new')
                            ->required(fn (Get $get): bool => $get('article_mode') === 'new'),
                        Textarea::make('description')
                            ->label('Deskripsi / Catatan Artikel')
                            ->default(fn (Order $record): ?string => $record->description)
                            ->visible(fn (Get $get): bool => $get('article_mode') === 'new')
                            ->rows(3),
                    ])
                    ->action(function (Order $record, array $data) {
                        $sample = app(SampleService::class)->initializeSampleFromOrder($record, $data);

                        Notification::make()
                            ->title('Sample v1 dan Artikel berhasil dibuat')
                            ->success()
                            ->send();

                        return redirect(SampleResource::getUrl('edit', ['record' => $sample->getKey()]));
                    }),
            ])
            ->emptyStateHeading('Tidak Ada Antrean Sample')
            ->emptyStateDescription('Semua pesanan yang disetujui klien sudah diproses sampelnya.')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }
}
