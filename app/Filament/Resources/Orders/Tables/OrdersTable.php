<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\WorkScheme;
use App\Filament\Resources\Samples\SampleResource;
use App\Models\Article;
use App\Models\Order;
use App\Services\Order\OrderQuotationService;
use App\Services\Order\QuoteDocumentRenderer;
use App\Services\Sample\SampleService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Daftar order lintas klien, dari permintaan klien sampai produksi massal.
 *
 * Owner memberi penawaran pada permintaan klien dan mengirim permintaan sample
 * setelah penawaran disetujui. Filter status "Penawaran Disetujui" adalah daftar
 * order yang siap masuk tahap sample.
 */
class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
                    ->label('Skema')
                    ->badge(),
                TextColumn::make('description')
                    ->label('Permintaan')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('estimated_cost')
                    ->label('Penawaran')
                    ->money('IDR')
                    ->placeholder('Belum ditawar')
                    ->sortable(),
                TextColumn::make('sample.code')
                    ->label('Sample')
                    ->placeholder('-')
                    ->description(fn (Order $record): ?string => $record->sampleRevision !== null
                        ? 'Revisi v'.$record->sampleRevision->version
                        : null),
                TextColumn::make('sampling_status')
                    ->label('Status Sampling')
                    ->badge()
                    ->state(function (Order $record): string {
                        if (! $record->sample) {
                            if ($record->status === OrderStatus::QUOTE_APPROVED) {
                                if ($record->sample_requested_at === null) {
                                    return 'Menunggu Permintaan Sample';
                                }

                                return 'Permintaan Sample Terkirim';
                            }

                            return $record->article_id ? 'Artikel Dibuat' : 'Belum Ada Artikel';
                        }
                        $rev = $record->sample->currentRevision;
                        if (! $rev) {
                            return 'Sample (Belum Ada Revisi)';
                        }

                        return sprintf('%s - %s', $rev->version_label, $rev->status->getLabel());
                    })
                    ->color(function (Order $record): string {
                        if (! $record->sample) {
                            if ($record->status === OrderStatus::QUOTE_APPROVED) {
                                return $record->sample_requested_at === null ? 'gray' : 'warning';
                            }

                            return 'gray';
                        }
                        $rev = $record->sample->currentRevision;

                        return $rev ? $rev->status->getColor() : 'gray';
                    }),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('start_date')
                    ->label('Mulai Project')
                    ->date()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Tenggat Selesai')
                    ->date()
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('work_scheme')
                    ->label('Skema')
                    ->options(WorkScheme::class),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(OrderStatus::class),
            ])
            ->recordActions([
                Action::make('sendSampleRequest')
                    ->label('Kirim Permintaan Sample')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim Permintaan Pembuatan Sample?')
                    ->modalDescription('Pesanan yang telah di-ACC penawarannya ini akan diteruskan ke antrean Tim Sample untuk dipersiapkan dan ditentukan artikelnya.')
                    ->visible(fn (Order $record): bool => auth()->user()?->can('requestSample', $record) ?? false)
                    ->action(function (Order $record): void {
                        app(OrderQuotationService::class)->requestSample($record, auth()->user());

                        Notification::make()
                            ->title('Permintaan sample berhasil dikirim ke Tim Sample')
                            ->success()
                            ->send();
                    }),
                Action::make('createArticleAndSample')
                    ->label('Tentukan Artikel & Mulai Sample')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->color('primary')
                    ->modalHeading('Tentukan Artikel & Inisialisasi Sample v1')
                    ->modalDescription('Pilih apakah pesanan ini menggunakan master artikel yang sudah ada milik klien, atau buat master artikel baru.')
                    ->visible(fn (Order $record): bool => auth()->user()?->can('createArticleAndSample', $record) ?? false)
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
                Action::make('viewQuote')
                    ->label('Dokumen Penawaran')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('info')
                    ->visible(fn (Order $record): bool => (bool) $record->quoted_at && (float) $record->estimated_cost > 0)
                    ->modalHeading(fn (Order $record): string => "Dokumen Penawaran — {$record->order_number}")
                    ->modalContent(fn (Order $record): HtmlString => new HtmlString(QuoteDocumentRenderer::renderHtml($record)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('4xl'),
                Action::make('provideQuote')
                    ->label('Berikan Penawaran')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('warning')
                    ->modalHeading('Penawaran untuk permintaan klien')
                    ->visible(fn (Order $record): bool => auth()->user()?->can('quote', $record) ?? false)
                    ->fillForm(fn (Order $record): array => [
                        'estimated_cost' => $record->estimated_cost,
                        'quote_notes' => $record->quote_notes,
                        'start_date' => $record->start_date?->format('Y-m-d'),
                        'due_date' => $record->due_date?->format('Y-m-d'),
                    ])
                    ->schema([
                        // Entri ini murni tampilan — dehydrated(false) menjaga datanya
                        // tidak ikut terkirim sebagai isi form penawaran.
                        Section::make('Permintaan Klien')
                            ->description('Permintaan asli klien — periksa sebelum menetapkan biaya.')
                            ->schema([
                                TextEntry::make('quantity')
                                    ->label('Jumlah Order Target')
                                    ->numeric()
                                    ->suffix(' pcs')
                                    ->placeholder('-')
                                    ->dehydrated(false),
                                TextEntry::make('work_scheme')
                                    ->label('Skema Maklon Diminta')
                                    ->badge()
                                    ->dehydrated(false),
                                TextEntry::make('start_date')
                                    ->label('Mulai Diminta Klien')
                                    ->date()
                                    ->placeholder('-')
                                    ->dehydrated(false),
                                TextEntry::make('due_date')
                                    ->label('Tenggat Diminta Klien')
                                    ->date()
                                    ->placeholder('-')
                                    ->dehydrated(false),
                                TextEntry::make('description')
                                    ->label('Deskripsi')
                                    ->placeholder('-')
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                                // Bucket s3 privat — biarkan Filament menerbitkan URL bertanda tangan.
                                ImageEntry::make('design_images')
                                    ->label('Gambar Design')
                                    ->disk(config('filesystems.default', 'local'))
                                    ->placeholder('Klien tidak melampirkan gambar.')
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        TextInput::make('estimated_cost')
                            ->label('Perkiraan Biaya (IDR)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        DatePicker::make('start_date')
                            ->label('Perkiraan Mulai Project (Disanggupi)')
                            ->helperText('Konfirmasi atau sesuaikan jadwal mulai pengerjaan.')
                            ->default(fn (Order $record) => $record->start_date)
                            ->native(false)
                            ->required(),
                        DatePicker::make('due_date')
                            ->label('Tenggat Selesai (Disanggupi)')
                            ->helperText('Konfirmasi atau sesuaikan estimasi tenggat waktu selesai produksi.')
                            ->default(fn (Order $record) => $record->due_date)
                            ->native(false)
                            ->afterOrEqual('start_date')
                            ->required(),
                        Textarea::make('quote_notes')
                            ->label('Catatan Penawaran')
                            ->columnSpanFull(),
                    ])
                    ->action(function (Order $record, array $data): void {
                        app(OrderQuotationService::class)->provideQuote(
                            $record,
                            (float) $data['estimated_cost'],
                            $data['quote_notes'] ?? null,
                            auth()->user(),
                            startDate: $data['start_date'] ?? null,
                            dueDate: $data['due_date'] ?? null,
                        );

                        Notification::make()
                            ->title('Penawaran dikirim ke klien')
                            ->success()
                            ->send();
                    }),
                Action::make('rejectRequest')
                    ->label('Tolak Pesanan')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Order $record): bool => auth()->user()?->can('rejectRequest', $record) ?? false)
                    ->modalHeading('Tolak Permintaan Pesanan')
                    ->modalDescription('Pesanan akan dibatalkan permanen dan tidak dapat diproses lebih lanjut.')
                    ->schema([
                        Textarea::make('cancellation_reason')
                            ->label('Alasan Penolakan')
                            ->placeholder('Tuliskan alasan mengapa pesanan ini ditolak (misal: kapasitas penuh, bahan tidak tersedia, dsb)...')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Order $record, array $data): void {
                        app(OrderQuotationService::class)->rejectRequest(
                            $record,
                            auth()->user(),
                            $data['cancellation_reason'],
                        );

                        Notification::make()
                            ->title('Pesanan Ditolak')
                            ->body('Pesanan telah dibatalkan dan ditutup.')
                            ->danger()
                            ->send();
                    }),

                Action::make('downloadDesigns')
                    ->label('Unduh Desain')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->tooltip('Unduh berkas lampiran desain order ini')
                    ->visible(fn (Order $record): bool => ! empty($record->design_images))
                    ->url(fn (Order $record): string => route('orders.designs.download', $record))
                    ->openUrlInNewTab(),
                ViewAction::make()
                    ->label('Lihat Detail')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray'),
                EditAction::make()
                    ->label('Ubah'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
