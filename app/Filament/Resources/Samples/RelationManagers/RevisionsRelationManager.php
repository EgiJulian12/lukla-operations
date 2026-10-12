<?php

namespace App\Filament\Resources\Samples\RelationManagers;

use App\Models\Component;
use App\Models\SampleRevision;
use App\Services\Sample\SampleService;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Revisi sample dengan versioning v1, v2, dst (FR-001, BR-005).
 *
 * Penambahan revisi dijalankan lewat SampleService supaya sampel induk ikut
 * diperbarui (revisi terkini dan statusnya). Persetujuan adalah aksi terpisah
 * yang hanya muncul untuk revisi yang sedang menunggu persetujuan dan bagi
 * pengguna yang berwenang.
 */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revisi Sample';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version')
                    ->label('Versi')
                    ->formatStateUsing(fn (int $state): string => 'v'.$state)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('time_studies_count')
                    ->label('Time Study')
                    ->counts('timeStudies')
                    ->numeric(),
                TextColumn::make('approver.name')
                    ->label('Disetujui Oleh')
                    ->placeholder('-'),
                TextColumn::make('approved_at')
                    ->label('Disetujui Pada')
                    ->dateTime()
                    ->placeholder('-'),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->limit(30),
            ])
            ->headerActions([
                Action::make('addRevision')
                    ->label('Tambah Revisi')
                    ->icon(Heroicon::OutlinedPlus)
                    ->form([
                        KeyValue::make('specs')
                            ->label('Spesifikasi')
                            ->keyLabel('Atribut')
                            ->valueLabel('Nilai'),
                        KeyValue::make('material_usage')
                            ->label('Pemakaian Bahan')
                            ->keyLabel('Bahan')
                            ->valueLabel('Jumlah')
                            ->helperText('Nilai bebas, misalnya 1.25 meter per unit.'),
                        Repeater::make('time_studies')
                            ->label('Time Study per Komponen')
                            ->schema([
                                Select::make('component_id')
                                    ->label('Komponen')
                                    ->options(fn (): array => Component::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->required(),
                                TextInput::make('duration_seconds')
                                    ->label('Durasi (detik)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),
                                TextInput::make('notes')
                                    ->label('Catatan'),
                            ])
                            ->addActionLabel('Tambah Komponen')
                            ->defaultItems(0)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Catatan Revisi')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data): void {
                        $service = app(SampleService::class);
                        $revision = $service->addRevision(
                            sample: $this->getOwnerRecord(),
                            specs: $data['specs'] ?? [],
                            materialUsage: $data['material_usage'] ?? [],
                            notes: $data['notes'] ?? null,
                        );

                        foreach ($data['time_studies'] ?? [] as $row) {
                            $service->recordTimeStudy(
                                revision: $revision,
                                component: Component::findOrFail($row['component_id']),
                                durationSeconds: (int) $row['duration_seconds'],
                                notes: $row['notes'] ?? null,
                            );
                        }
                    })
                    ->successNotificationTitle('Revisi ditambahkan'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Setujui')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (SampleRevision $record): bool => $record->status->isApprovable()
                        && (bool) auth()->user()?->can('approve', $record))
                    ->action(fn (SampleRevision $record) => app(SampleService::class)->approveRevision($record))
                    ->successNotificationTitle('Revisi disetujui'),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (SampleRevision $record): bool => $record->status->isApprovable()
                        && (bool) auth()->user()?->can('reject', $record))
                    ->form([
                        Textarea::make('reason')
                            ->label('Alasan Penolakan')
                            ->required(),
                    ])
                    ->action(fn (SampleRevision $record, array $data) => app(SampleService::class)
                        ->rejectRevision($record, $data['reason']))
                    ->successNotificationTitle('Revisi ditolak'),
            ])
            ->defaultSort('version', 'desc');
    }
}
