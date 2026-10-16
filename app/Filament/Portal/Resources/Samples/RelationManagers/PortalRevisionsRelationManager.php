<?php

namespace App\Filament\Portal\Resources\Samples\RelationManagers;

use App\Filament\Resources\Samples\RelationManagers\RevisionsRelationManager;
use App\Models\SampleRevision;
use App\Services\Sample\SampleService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Relation manager revisi sample khusus portal klien (Langkah 5 — Review Sample).
 *
 * Klien maklon hanya memiliki tiga aksi:
 * 1. Lihat detail revisi (view)
 * 2. Setujui sample (ACC) — hanya jika status IN_REVIEW
 * 3. Tolak / Minta revisi — hanya jika status IN_REVIEW, wajib isi alasan
 *
 * Aksi internal (edit, submit review, create revision) tidak terekspos ke portal.
 */
class PortalRevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revisi Sample & Evaluasi';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version')
                    ->label('Versi')
                    ->formatStateUsing(fn (int $state): string => 'v'.$state)
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                ImageColumn::make('result_image_path')
                    ->label('Foto Hasil Sample')
                    ->disk(config('filesystems.default', 'local'))
                    ->circular()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('approver.name')
                    ->label('Disetujui Oleh')
                    ->placeholder('-'),
                TextColumn::make('approved_at')
                    ->label('Disetujui Pada')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->limit(50),
            ])
            ->recordActions([
                ViewAction::make('viewRevision')
                    ->label('Lihat Detail Revisi')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalWidth('6xl')
                    ->modalHeading(fn (SampleRevision $record): string => sprintf(
                        'Detail Revisi %s — %s',
                        $record->version_label,
                        $record->status->getLabel(),
                    ))
                    ->mutateRecordDataUsing(function (SampleRevision $record, array $data): array {
                        $data['component_time_studies'] = $record->timeStudies()
                            ->with('component')
                            ->get()
                            ->map(fn ($ts) => [
                                'component_name' => $ts->component?->name ?? '',
                                'duration_seconds' => $ts->duration_seconds,
                                'notes' => $ts->notes,
                            ])
                            ->all();

                        $internalKeys = ['design_images', 'description', 'order_number'];
                        $rawSpecs = $data['specs'] ?? [];
                        $cleanSpecs = [];
                        foreach ($rawSpecs as $k => $v) {
                            if (! in_array($k, $internalKeys, true) && is_scalar($v)) {
                                $cleanSpecs[$k] = (string) $v;
                            }
                        }
                        $data['specs'] = $cleanSpecs;

                        $designImages = $record->specs['design_images']
                            ?? $record->sample?->designImages()
                            ?? [];
                        $data['design_images'] = is_array($designImages) ? $designImages : [$designImages];
                        $data['design_description'] = $record->specs['description']
                            ?? $record->sample?->designDescription()
                            ?? 'Tidak ada deskripsi khusus.';

                        $data['material_usage_items'] = RevisionsRelationManager::hydrateMaterialUsageItems($record->material_usage ?? []);

                        return $data;
                    })
                    ->form(fn () => $this->getReadOnlyFormSchema()),

                Action::make('approve')
                    ->label('Setujui Sample (ACC)')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Setujui Versi Sample Ini?')
                    ->modalDescription('Setelah disetujui, versi ini akan menjadi acuan spesifikasi untuk produksi massal. Tindakan ini tidak dapat dibatalkan.')
                    ->visible(fn (SampleRevision $record): bool => $record->status->isApprovable()
                        && (bool) auth()->user()?->can('approve', $record))
                    ->action(function (SampleRevision $record): void {
                        app(SampleService::class)->approveRevision($record, auth()->user());

                        Notification::make()
                            ->title('Sample Berhasil Disetujui (ACC)')
                            ->body('Versi sample ini telah Anda setujui sebagai acuan produksi massal.')
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Minta Revisi / Tolak')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Minta Perbaikan Sample')
                    ->modalDescription('Jelaskan bagian mana yang perlu diperbaiki. Tim Sample akan membuat revisi baru berdasarkan catatan Anda.')
                    ->visible(fn (SampleRevision $record): bool => $record->status->isApprovable()
                        && (bool) auth()->user()?->can('reject', $record))
                    ->form([
                        Textarea::make('reason')
                            ->label('Catatan Perbaikan / Alasan Penolakan')
                            ->placeholder('Jelaskan bagian yang perlu diperbaiki, misal: warna tidak sesuai, ukuran kerah terlalu besar, jahitan saku kurang rapi...')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (SampleRevision $record, array $data): void {
                        app(SampleService::class)->rejectRevision($record, $data['reason']);

                        Notification::make()
                            ->title('Permintaan Revisi Dikirim')
                            ->body('Tim Sample akan membuat versi perbaikan berdasarkan catatan Anda.')
                            ->warning()
                            ->send();
                    }),
            ])
            ->defaultSort('version', 'desc');
    }

    /**
     * Skema form read-only untuk menampilkan detail revisi kepada klien.
     *
     * Menampilkan perbandingan berdampingan antara Acuan Desain dari Klien
     * dengan Foto Hasil Fisik Sample dari Pabrik, beserta spesifikasi & kebutuhan bahan.
     *
     * @return array<int, Section>
     */
    private function getReadOnlyFormSchema(): array
    {
        return [
            Section::make('Komparasi: Acuan Desain vs Hasil Sample Fisik')
                ->description('Bandingkan desain acuan yang diajukan dengan foto fisik hasil pengerjaan tim sample.')
                ->columns(2)
                ->schema([
                    Section::make('Acuan Desain dari Klien')
                        ->schema([
                            FileUpload::make('design_images')
                                ->label('Gambar Desain Acuan')
                                ->multiple()
                                ->image()
                                ->disk(config('filesystems.default', 'local'))
                                ->imagePreviewHeight('180')
                                ->disabled()
                                ->dehydrated(false),
                            Textarea::make('design_description')
                                ->label('Deskripsi Permintaan Desain')
                                ->rows(3)
                                ->disabled()
                                ->dehydrated(false),
                        ])
                        ->columnSpan(1),

                    Section::make('Hasil Fisik Sample & Review Pabrik')
                        ->schema([
                            FileUpload::make('result_image_path')
                                ->label('Foto Hasil Sample Fisik')
                                ->image()
                                ->disk(config('filesystems.default', 'local'))
                                ->imagePreviewHeight('180')
                                ->disabled(),
                            Textarea::make('notes')
                                ->label('Catatan Hasil / Feedback Tim')
                                ->rows(3)
                                ->disabled(),
                        ])
                        ->columnSpan(1),
                ]),

            Section::make('Spesifikasi & Kebutuhan Bahan Baku')
                ->description('Spesifikasi fisik produk dan estimasi kebutuhan bahan baku per 1 pcs.')
                ->collapsible()
                ->columns(2)
                ->schema([
                    KeyValue::make('specs')
                        ->label('Spesifikasi Fisik Produk')
                        ->keyLabel('Atribut')
                        ->valueLabel('Nilai')
                        ->disabled(),
                    Repeater::make('material_usage_items')
                        ->label('Estimasi Pemakaian Bahan Baku & Aksesoris (Per 1 Pcs)')
                        ->schema([
                            TextInput::make('item_name')
                                ->label('Nama Bahan / Aksesoris')
                                ->disabled()
                                ->columnSpan(5),
                            TextInput::make('qty')
                                ->label('Konsumsi')
                                ->disabled()
                                ->columnSpan(3),
                            TextInput::make('uom')
                                ->label('Satuan')
                                ->disabled()
                                ->columnSpan(4),
                        ])
                        ->columns(12)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false),
                ]),

            Section::make('Komponen & Waktu Pengerjaan')
                ->description('Daftar tahapan operasi jahit beserta durasi yang dicatat oleh Tim Sample.')
                ->collapsible()
                ->schema([
                    Repeater::make('component_time_studies')
                        ->label('Tahapan Operasi Jahit')
                        ->schema([
                            TextInput::make('component_name')
                                ->label('Nama Komponen / Operasi')
                                ->columnSpan(5)
                                ->disabled(),
                            TextInput::make('duration_seconds')
                                ->label('Waktu (Detik)')
                                ->suffix('detik')
                                ->columnSpan(3)
                                ->disabled(),
                            TextInput::make('notes')
                                ->label('Catatan')
                                ->columnSpan(4)
                                ->disabled(),
                        ])
                        ->columns(12)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false),
                ]),
        ];
    }
}
