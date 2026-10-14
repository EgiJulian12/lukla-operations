<?php

namespace App\Filament\Resources\MaterialDispatches\Tables;

use App\Enums\DispatchStatus;
use App\Enums\UserRole;
use App\Models\MaterialDispatch;
use App\Services\Inventory\MaterialDispatchService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaterialDispatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dispatch_number')
                    ->label('No. Pengiriman')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Pesanan')
                    ->searchable(),
                TextColumn::make('material.name')
                    ->label('Bahan')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('uom')
                    ->label('Satuan'),
                TextColumn::make('status')
                    ->label('Status Pengantaran')
                    ->badge(),
                TextColumn::make('requested_at')
                    ->label('Diajukan')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('completed_at')
                    ->label('Selesai')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(DispatchStatus::class),
            ])
            ->recordActions([
                // 1. Setujui Pengajuan
                Action::make('approve')
                    ->label('Setujui')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('info')
                    ->visible(fn (MaterialDispatch $record): bool => $record->status === DispatchStatus::REQUESTED)
                    ->requiresConfirmation()
                    ->action(function (MaterialDispatch $record, MaterialDispatchService $service) {
                        $service->approve($record, auth()->id() ?? 1);
                        Notification::make()->title('Pengajuan pengiriman bahan disetujui')->success()->send();
                    }),

                // 2. Tolak Pengajuan
                Action::make('reject')
                    ->label('Tolak')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (MaterialDispatch $record): bool => $record->status === DispatchStatus::REQUESTED)
                    ->form([
                        Textarea::make('reason')
                            ->label('Alasan Penolakan')
                            ->required(),
                    ])
                    ->action(function (MaterialDispatch $record, array $data, MaterialDispatchService $service) {
                        $service->reject($record, auth()->id() ?? 1, $data['reason']);
                        Notification::make()->title('Pengajuan pengiriman bahan ditolak')->danger()->send();
                    }),

                // 3. Kirim dari Gudang
                Action::make('dispatch')
                    ->label('Kirim Bahan')
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('primary')
                    ->visible(fn (MaterialDispatch $record): bool => $record->status === DispatchStatus::APPROVED)
                    ->requiresConfirmation()
                    ->action(function (MaterialDispatch $record, MaterialDispatchService $service) {
                        $service->dispatch($record, auth()->id() ?? 1);
                        Notification::make()->title('Bahan telah dikirim dari gudang menuju produksi')->success()->send();
                    }),

                // 4. Konfirmasi Penerimaan di Produksi (Hanya Bagian Produksi / Owner)
                Action::make('confirm_received')
                    ->label('Konfirmasi Diterima')
                    ->icon(Heroicon::OutlinedInboxArrowDown)
                    ->color('success')
                    ->visible(function (MaterialDispatch $record): bool {
                        if (! in_array($record->status, [DispatchStatus::DISPATCHED, DispatchStatus::PENDING_DELIVERY], true)) {
                            return false;
                        }

                        $user = auth()->user();

                        return $user?->hasRole(UserRole::PRODUCTION_LEAD)
                            || $user?->hasRole(UserRole::OWNER_EXECUTIVE);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Penerimaan Bahan di Lantai Produksi')
                    ->modalDescription('Pastikan fisik bahan/komponen siap jahit telah tiba dan sesuai dengan surat jalan.')
                    ->action(function (MaterialDispatch $record, MaterialDispatchService $service) {
                        $service->confirmReceived($record, auth()->id() ?? 1);
                        Notification::make()->title('Bahan berhasil diterima di area produksi')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
