<?php

namespace App\Filament\Portal\Resources\Samples\RelationManagers;

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
                        $userSpecs = array_diff_key($data['specs'] ?? [], array_flip($internalKeys));
                        if (! empty($userSpecs)) {
                            $data['specs'] = $userSpecs;
                        }

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
     * Spesifikasi dan estimasi bahan ditampilkan read-only, dan daftar
     * komponen time study ditampilkan tanpa stopwatch interaktif.
     *
     * @return array<int, Section>
     */
    private function getReadOnlyFormSchema(): array
    {
        return [
            Section::make('Spesifikasi & Estimasi Bahan')
                ->description('Spesifikasi fisik produk dan perkiraan pemakaian bahan per unit.')
                ->collapsible()
                ->columns(2)
                ->schema([
                    KeyValue::make('specs')
                        ->label('Spesifikasi Produk')
                        ->keyLabel('Atribut')
                        ->valueLabel('Nilai'),
                    KeyValue::make('material_usage')
                        ->label('Estimasi Pemakaian Bahan')
                        ->keyLabel('Bahan')
                        ->valueLabel('Jumlah & Satuan'),
                ]),

            Section::make('Komponen & Waktu Pengerjaan')
                ->description('Daftar komponen jahit beserta waktu pengerjaan yang dicatat oleh Tim Sample.')
                ->schema([
                    Repeater::make('component_time_studies')
                        ->label('Komponen')
                        ->schema([
                            TextInput::make('component_name')
                                ->label('Nama Komponen')
                                ->columnSpan(5),
                            TextInput::make('duration_seconds')
                                ->label('Waktu (Detik)')
                                ->suffix('detik')
                                ->columnSpan(3),
                            TextInput::make('notes')
                                ->label('Catatan')
                                ->columnSpan(4),
                        ])
                        ->columns(12)
                        ->addable(false)
                        ->deletable(false),
                ]),

            Section::make('Hasil Akhir & Catatan')
                ->description('Dokumentasi foto hasil pembuatan sample fisik.')
                ->columns(2)
                ->schema([
                    FileUpload::make('result_image_path')
                        ->label('Foto Hasil Sample')
                        ->image()
                        ->disk(config('filesystems.default', 'local'))
                        ->imagePreviewHeight('200')
                        ->columnSpan(1),
                    Textarea::make('notes')
                        ->label('Catatan Review')
                        ->rows(5)
                        ->columnSpan(1),
                ]),
        ];
    }
}
