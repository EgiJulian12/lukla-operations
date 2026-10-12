<?php

namespace App\Filament\Resources\ProductionLines\RelationManagers;

use App\Enums\UserRole;
use App\Models\LineAssignment;
use App\Models\User;
use App\Services\Production\ProductionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Alokasi penjahit pada sebuah line (FR-009).
 *
 * Penugasan diakhiri dengan menutup barisnya, bukan menghapusnya, sehingga
 * riwayat alokasi tetap dapat diaudit.
 */
class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Penugasan Penjahit';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Penjahit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('assigned_at')
                    ->label('Ditugaskan')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('unassigned_at')
                    ->label('Diakhiri')
                    ->dateTime()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (LineAssignment $record): string => $record->isActive() ? 'Aktif' : 'Selesai')
                    ->color(fn (string $state): string => $state === 'Aktif' ? 'success' : 'gray'),
            ])
            ->headerActions([
                Action::make('assign')
                    ->label('Tugaskan Penjahit')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->form([
                        Select::make('user_id')
                            ->label('Penjahit')
                            ->options(fn (): array => User::query()
                                ->role(UserRole::TAILOR_OPERATOR->value)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(fn (array $data) => app(ProductionService::class)->assignOperator(
                        line: $this->getOwnerRecord(),
                        operator: User::findOrFail($data['user_id']),
                    ))
                    ->successNotificationTitle('Penjahit ditugaskan'),
            ])
            ->recordActions([
                Action::make('end')
                    ->label('Akhiri')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (LineAssignment $record): bool => $record->isActive())
                    ->action(fn (LineAssignment $record) => app(ProductionService::class)->unassignOperator($record))
                    ->successNotificationTitle('Penugasan diakhiri'),
            ])
            ->defaultSort('assigned_at', 'desc');
    }
}
