<?php

namespace App\Filament\Resources\Samples\RelationManagers;

use App\Enums\SampleStatus;
use App\Models\Component;
use App\Models\SampleRevision;
use App\Services\Sample\SampleService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Ruang Kerja Revisi Sample Terpadu (FR-001 sampai FR-004, Skenario Uji 04).
 *
 * Menyatukan penentuan komponen, digital stopwatch, pemakaian bahan,
 * foto hasil akhir, review kearsipan, serta mekanisme cloning v1 -> v2.
 */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revisi & Ruang Kerja Sample';

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
                    ->label('Foto Hasil')
                    ->disk(config('filesystems.default', 'local'))
                    ->circular()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('time_studies_count')
                    ->label('Komponen')
                    ->counts('timeStudies')
                    ->numeric()
                    ->badge()
                    ->color('info'),
                TextColumn::make('total_duration')
                    ->label('Total Waktu')
                    ->state(fn (SampleRevision $record): string => sprintf('%d dtk (%s mnt)', $record->totalTimeStudySeconds(), round($record->totalTimeStudySeconds() / 60, 1))),
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
                    ->limit(35),
            ])
            ->headerActions([
                Action::make('addRevision')
                    ->label('Tambah Revisi Baru (v1)')
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalWidth('6xl')
                    ->modalHeading('Tambah Versi Sample Baru')
                    ->visible(fn (): bool => $this->getOwnerRecord()->revisions()->count() === 0 && (bool) auth()->user()?->can('create', SampleRevision::class))
                    ->form(fn () => self::getRevisionFormSchema($this->getOwnerRecord()))
                    ->action(function (array $data): void {
                        $service = app(SampleService::class);
                        $revision = $service->addRevision(
                            sample: $this->getOwnerRecord(),
                            specs: $data['specs'] ?? [],
                            materialUsage: $data['material_usage'] ?? [],
                            notes: $data['notes'] ?? null,
                            resultImagePath: $data['result_image_path'] ?? null,
                        );

                        if (! empty($data['component_time_studies'])) {
                            $service->syncComponentsAndTimeStudies($revision, $data['component_time_studies']);
                        }

                        Notification::make()
                            ->title('Revisi sample berhasil dibuat')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make('editRevision')
                    ->label('Input Komponen, Waktu & Hasil')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('primary')
                    ->modalWidth('6xl')
                    ->modalHeading(fn (SampleRevision $record): string => sprintf('Pengerjaan Sample %s (DRAFT)', $record->version_label))
                    ->visible(fn (SampleRevision $record): bool => $record->status === SampleStatus::DRAFT && (bool) auth()->user()?->can('update', $record))
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
                        $userSpecs = array_diff_key($data['specs'] ?? [], array_flip($internalKeys));
                        if (empty($userSpecs)) {
                            $data['specs'] = [
                                'Bahan Utama' => 'Katun Combed 30s Reaktif',
                                'Warna' => 'Hitam Solid (Jet Black)',
                                'Gramasi Kain' => '150 - 160 GSM',
                                'Pola / Fitting' => 'Oversize Fit (Pola Drop Shoulder)',
                                'Model Kerah' => 'O-Neck Rib Katun 2.5 cm',
                                'Aksesoris / Sablon' => 'Sablon Plastisol Doff & Label Woven',
                            ];
                        } else {
                            $data['specs'] = $userSpecs;
                        }

                        if (empty($data['material_usage'])) {
                            $data['material_usage'] = [
                                'Kain Utama (Combed 30s)' => '1.25 meter',
                                'Rib Leher (Katun)' => '0.10 meter',
                                'Benang Jahit' => '1 spool / 50 pcs',
                                'Label Woven & Care Label' => '1 set',
                            ];
                        }

                        return $data;
                    })
                    ->form(fn () => self::getRevisionFormSchema($this->getOwnerRecord()))
                    ->action(function (SampleRevision $record, array $data): void {
                        $record->update([
                            'specs' => $data['specs'] ?? [],
                            'material_usage' => $data['material_usage'] ?? [],
                            'result_image_path' => $data['result_image_path'] ?? null,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        if (isset($data['component_time_studies'])) {
                            app(SampleService::class)->syncComponentsAndTimeStudies($record, $data['component_time_studies']);
                        }

                        Notification::make()
                            ->title('Data pengerjaan sample berhasil disimpan')
                            ->success()
                            ->send();
                    }),

                ViewAction::make('viewRevision')
                    ->label('Lihat Detail Hasil & Waktu')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalWidth('6xl')
                    ->modalHeading(fn (SampleRevision $record): string => sprintf('Detail & Hasil Sample %s (%s)', $record->version_label, $record->status->getLabel()))
                    ->visible(fn (): bool => true)
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
                        $userSpecs = array_diff_key($data['specs'] ?? [], array_flip($internalKeys));
                        if (! empty($userSpecs)) {
                            $data['specs'] = $userSpecs;
                        }

                        return $data;
                    })
                    ->form(fn () => self::getRevisionFormSchema($this->getOwnerRecord())),

                Action::make('submitReview')
                    ->label('Submit Review ke Klien & Owner')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Ajukan Review Sample?')
                    ->modalDescription('Pastikan komponen artikel dan waktu stopwatch telah diisi dengan lengkap sebelum diajukan ke Klien & Owner.')
                    ->visible(fn (SampleRevision $record): bool => $record->status === SampleStatus::DRAFT && (bool) auth()->user()?->can('update', $record))
                    ->action(function (SampleRevision $record): void {
                        if ($record->timeStudies()->count() === 0) {
                            Notification::make()
                                ->title('Komponen Belum Diinput')
                                ->body('Minimal 1 komponen beserta waktu pengerjaan wajib diinput sebelum mengajukan review.')
                                ->danger()
                                ->send();

                            return;
                        }

                        app(SampleService::class)->submitForReview($record);

                        Notification::make()
                            ->title('Revisi sample diajukan untuk review')
                            ->success()
                            ->send();
                    }),

                Action::make('createRevision')
                    ->label('Kerjakan Revisi Baru (v+1)')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->modalHeading(fn (SampleRevision $record): string => sprintf('Kerjakan Revisi Sample Baru dari %s', $record->version_label))
                    ->modalDescription('Versi saat ini akan otomatis diarsipkan (read-only). Seluruh spesifikasi, daftar komponen, dan waktu stopwatch akan disalin ke versi baru (DRAFT) untuk disesuaikan.')
                    ->visible(fn (SampleRevision $record): bool => $record->canBeRevised() && (bool) auth()->user()?->can('update', $record))
                    ->form([
                        Textarea::make('notes')
                            ->label('Catatan Perubahan / Alasan Revisi')
                            ->placeholder('Contoh: Mengubah ukuran lingkar kerah dan durasi jahit saku depan...')
                            ->required(),
                    ])
                    ->action(function (SampleRevision $record, array $data): void {
                        $newRevision = app(SampleService::class)->createRevisionFromPrevious(
                            previous: $record,
                            notes: $data['notes'] ?? null,
                        );

                        Notification::make()
                            ->title(sprintf('Revisi %s berhasil dibuat (DRAFT)', $newRevision->version_label))
                            ->success()
                            ->send();
                    }),

                Action::make('approve')
                    ->label('Setujui Sample (ACC)')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Setujui Versi Sample Ini?')
                    ->modalDescription('Versi ini akan disahkan sebagai spesifikasi acuan produksi massal.')
                    ->visible(fn (SampleRevision $record): bool => $record->status->isApprovable()
                        && (bool) auth()->user()?->can('approve', $record))
                    ->action(function (SampleRevision $record): void {
                        app(SampleService::class)->approveRevision($record, auth()->user());

                        Notification::make()
                            ->title('Sample berhasil disetujui (APPROVED)')
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Tolak')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Tolak Versi Sample')
                    ->visible(fn (SampleRevision $record): bool => $record->status->isApprovable()
                        && (bool) auth()->user()?->can('reject', $record))
                    ->form([
                        Textarea::make('reason')
                            ->label('Alasan Penolakan / Catatan Perbaikan')
                            ->placeholder('Tuliskan catatan perbaikan yang perlu diperbaiki oleh Tim Sample...')
                            ->required(),
                    ])
                    ->action(function (SampleRevision $record, array $data): void {
                        app(SampleService::class)->rejectRevision($record, $data['reason']);

                        Notification::make()
                            ->title('Revisi sample ditolak')
                            ->danger()
                            ->send();
                    }),
            ])
            ->defaultSort('version', 'desc');
    }

    /**
     * Skema terpadu pengerjaan sample dalam satu layar.
     */
    public static function getRevisionFormSchema($ownerRecord): array
    {
        return [
            Section::make('Section 1 — Spesifikasi & Estimasi Pemakaian Bahan')
                ->description('Tentukan spesifikasi fisik (Bahan, Warna, Pola) dan estimasi kebutuhan bahan baku per 1 pcs produk.')
                ->collapsible()
                ->columns(2)
                ->schema([
                    KeyValue::make('specs')
                        ->label('Spesifikasi Produk')
                        ->helperText('Contoh: Atribut = Bahan Utama, Nilai = Katun Combed 30s Reaktif.')
                        ->keyLabel('Atribut Spesifikasi')
                        ->keyPlaceholder('Contoh: Bahan Utama, Warna, Pola...')
                        ->valueLabel('Nilai / Keterangan')
                        ->valuePlaceholder('Contoh: Katun Combed 30s, Hitam Solid...')
                        ->default([
                            'Bahan Utama' => 'Katun Combed 30s Reaktif',
                            'Warna' => 'Hitam Solid (Jet Black)',
                            'Gramasi Kain' => '150 - 160 GSM',
                            'Pola / Fitting' => 'Oversize Fit (Pola Drop Shoulder)',
                            'Model Kerah' => 'O-Neck Rib Katun 2.5 cm',
                            'Aksesoris / Sablon' => 'Sablon Plastisol Doff & Label Woven',
                        ]),
                    KeyValue::make('material_usage')
                        ->label('Estimasi Pemakaian Bahan per Unit')
                        ->helperText('Contoh: Nama Bahan = Kain Utama, Jumlah & Satuan = 1.25 meter.')
                        ->keyLabel('Nama Bahan')
                        ->keyPlaceholder('Contoh: Kain Utama, Rib Leher, Benang...')
                        ->valueLabel('Jumlah & Satuan per Pcs')
                        ->valuePlaceholder('Contoh: 1.25 meter, 0.10 meter, 1 set...')
                        ->default([
                            'Kain Utama (Combed 30s)' => '1.25 meter',
                            'Rib Leher (Katun)' => '0.10 meter',
                            'Benang Jahit' => '1 spool / 50 pcs',
                            'Label Woven & Care Label' => '1 set',
                        ]),
                ]),

            Section::make('Section 2 — Penentuan Komponen & Waktu Pengerjaan (Stopwatch)')
                ->description('Pecah produk menjadi komponen jahit dan catat waktu pengerjaannya menggunakan Digital Stopwatch.')
                ->schema([
                    Repeater::make('component_time_studies')
                        ->label('Daftar Komponen & Time Study')
                        ->schema([
                            TextInput::make('component_name')
                                ->label('Nama Komponen')
                                ->placeholder('Contoh: Badan Depan, Kerah, Saku...')
                                ->datalist(function () use ($ownerRecord): array {
                                    if (! $ownerRecord?->article_id) {
                                        return [];
                                    }

                                    return Component::query()
                                        ->where('article_id', $ownerRecord->article_id)
                                        ->pluck('name')
                                        ->all();
                                })
                                ->required()
                                ->columnSpan(4),
                            ViewField::make('stopwatch')
                                ->label('Stopwatch')
                                ->view('filament.forms.components.digital-stopwatch')
                                ->columnSpan(3),
                            TextInput::make('duration_seconds')
                                ->label('Waktu (Detik)')
                                ->numeric()
                                ->minValue(1)
                                ->placeholder('0')
                                ->suffix('detik')
                                ->required()
                                ->columnSpan(2),
                            TextInput::make('notes')
                                ->label('Catatan Komponen')
                                ->placeholder('Instruksi atau catatan...')
                                ->columnSpan(3),
                        ])
                        ->columns(12)
                        ->defaultItems(1)
                        ->addActionLabel('Tambah Komponen Baru')
                        ->collapsible(),
                ]),

            Section::make('Section 3 — Hasil Akhir & Catatan Review')
                ->description('Upload foto dokumentasi fisik hasil pembuatan sample dan catatan evaluasi.')
                ->columns(2)
                ->schema([
                    FileUpload::make('result_image_path')
                        ->label('Foto Hasil Akhir Sample')
                        ->image()
                        ->directory('sample-results')
                        ->disk(config('filesystems.default', 'local'))
                        ->imagePreviewHeight('200')
                        ->columnSpan(1),
                    Textarea::make('notes')
                        ->label('Catatan Sample / Feedback Review')
                        ->placeholder('Tuliskan catatan hasil uji coba atau feedback dari tim...')
                        ->rows(5)
                        ->columnSpan(1),
                ]),
        ];
    }
}
