<?php

namespace App\Filament\Resources\OrderMaterialFlows\Tables;

use App\Enums\MaterialResponsibility;
use App\Enums\OrderMaterialStatus;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Models\Order;
use App\Models\User;
use App\Services\Inventory\OrderMaterialFlowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderMaterialFlowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Nomor Order')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('work_scheme')
                    ->label('Skema Maklon')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Target Qty')
                    ->numeric()
                    ->formatStateUsing(fn ($state): string => number_format($state).' pcs')
                    ->sortable(),
                TextColumn::make('warehouse_material_status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('fulfillment_progress')
                    ->label('Kesiapan Stok')
                    ->state(fn (Order $record): string => sprintf(
                        '%.1f%% (%d/%d item)',
                        $record->materialFulfillmentPercentage(),
                        $record->materialRequirements->where(fn ($r) => $r->isFulfilled())->count(),
                        $record->materialRequirements->count()
                    ))
                    ->badge()
                    ->color(function (Order $record): string {
                        $pct = $record->materialFulfillmentPercentage();
                        if ($pct >= 100) {
                            return 'success';
                        }
                        if ($pct > 0) {
                            return 'warning';
                        }

                        return 'gray';
                    }),
                TextColumn::make('due_date')
                    ->label('Tenggat')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('work_scheme')
                    ->label('Skema Maklon')
                    ->options(WorkScheme::class),
                SelectFilter::make('warehouse_material_status')
                    ->label('Status Alur Bahan')
                    ->options(OrderMaterialStatus::class),
            ])
            ->recordActions([
                // 1. Aksi Modal Pratinjau Daftar Barang (Read-Only Preview)
                Action::make('manageComponents')
                    ->label('Daftar Barang & Bahan')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->color('primary')
                    ->button()
                    ->modalWidth('5xl')
                    ->modalHeading(fn (Order $record): string => "Daftar Barang & Kebutuhan Bahan — Order #{$record->order_number}")
                    ->modalDescription(fn (Order $record): string => "Klien: {$record->client?->name} • Skema: {$record->work_scheme?->getLabel()} • Target Produksi: ".number_format($record->quantity).' pcs')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->schema([
                        RepeatableEntry::make('materialRequirements')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Nama Bahan / Item'),
                                TableColumn::make('Tanggungan')->alignment(Alignment::Center),
                                TableColumn::make('Kebutuhan Order')->alignment(Alignment::End),
                                TableColumn::make('Diterima Gudang')->alignment(Alignment::End),
                                TableColumn::make('Kekurangan')->alignment(Alignment::End),
                                TableColumn::make('Status')->alignment(Alignment::Center),
                            ])
                            ->schema([
                                TextEntry::make('item_name')
                                    ->weight('bold')
                                    ->helperText(fn ($record): string => ($record->item_type?->value === 'fabric' ? 'Kain' : ($record->item_type?->value === 'accessory' ? 'Aksesoris' : 'Item')).' • Satuan: '.$record->uom),
                                TextEntry::make('responsibility')
                                    ->badge(),
                                TextEntry::make('required_quantity')
                                    ->numeric()
                                    ->formatStateUsing(fn ($state, $record): string => number_format((float) $state, 2).' '.$record->uom),
                                TextEntry::make('received_quantity')
                                    ->numeric()
                                    ->color('success')
                                    ->weight('bold')
                                    ->formatStateUsing(fn ($state, $record): string => number_format((float) $state, 2).' '.$record->uom),
                                TextEntry::make('missing')
                                    ->state(fn ($record): string => $record->missingQuantity() > 0 ? '-'.number_format($record->missingQuantity(), 2).' '.$record->uom : 'Terpenuhi')
                                    ->color(fn ($record): string => $record->missingQuantity() > 0 ? 'danger' : 'success')
                                    ->weight('bold'),
                                TextEntry::make('status')
                                    ->badge(),
                            ])
                            ->placeholder('Belum ada rincian bahan & aksesoris untuk pesanan ini.'),
                    ]),

                // 2. Aksi: Pengajuan PO (Tanggungan Perusahaan)
                Action::make('requestPo')
                    ->label('Ajukan PO')
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->color('purple')
                    ->modalWidth('4xl')
                    ->visible(fn (Order $record): bool => static::canManageWarehouseFlow() && in_array($record->warehouse_material_status, [
                        OrderMaterialStatus::EMPTY,
                        OrderMaterialStatus::CLIENT_CONFIRMATION,
                    ], true) && $record->hasCompanyMaterials())
                    ->modalHeading(fn (Order $record): string => "Pengajuan PO Bahan — Order #{$record->order_number}")
                    ->modalDescription('Susun pengajuan pesanan pembelian (PO) bahan/aksesoris ke bagian Finance dengan status Diajukan (Submitted).')
                    ->fillForm(function (Order $record): array {
                        $now = now();
                        $data = [
                            'po_number' => 'PO-'.$now->format('Y-d-H-i'),
                            'supplier' => '',
                            'notes' => '',
                        ];

                        foreach ($record->materialRequirements as $r) {
                            if (in_array($r->responsibility, [MaterialResponsibility::COMPANY, MaterialResponsibility::PARTIAL], true)) {
                                $data['price_'.$r->id] = 0;
                            }
                        }

                        return $data;
                    })
                    ->form(function (Order $record): array {
                        $companyRequirements = $record->materialRequirements->filter(
                            fn ($r) => in_array($r->responsibility, [MaterialResponsibility::COMPANY, MaterialResponsibility::PARTIAL], true)
                        );

                        $itemsList = [];
                        foreach ($companyRequirements as $r) {
                            $missing = $r->missingQuantity();
                            $qty = $missing > 0 ? (float) $missing : (float) $r->required_quantity;

                            $itemsList[] = Grid::make(2)->schema([
                                TextEntry::make('item_info_'.$r->id)
                                    ->label($r->item_name)
                                    ->state(sprintf(
                                        'Kebutuhan: %s %s (Kurang: %s %s) • Satuan: %s',
                                        number_format($r->required_quantity, 2),
                                        $r->uom,
                                        number_format($missing, 2),
                                        $r->uom,
                                        $r->uom
                                    )),
                                TextInput::make('price_'.$r->id)
                                    ->label("Estimasi Harga Satuan ({$r->item_name})")
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->helperText("Jumlah pesan: {$qty} {$r->uom}"),
                            ]);
                        }

                        return [
                            Grid::make(2)->schema([
                                TextInput::make('po_number')
                                    ->label('Nomor PO')
                                    ->required()
                                    ->helperText('Format: PO-tahun-tanggal-jam-menit'),
                                TextInput::make('supplier')
                                    ->label('Pemasok / Supplier')
                                    ->placeholder('Masukkan nama supplier pemasok...')
                                    ->required(),
                            ]),
                            Textarea::make('notes')
                                ->label('Catatan Pengajuan PO')
                                ->placeholder('Sebutkan keterangan tambahan untuk bagian Finance/Pemasok...')
                                ->rows(2),
                            Section::make('Rincian Barang yang Diajukan (Bahan Pesanan)')
                                ->description('Daftar barang kebutuhan pesanan ini yang menjadi tanggungan perusahaan.')
                                ->schema($itemsList),
                        ];
                    })
                    ->action(function (Order $record, array $data): void {
                        $itemPrices = [];
                        foreach ($record->materialRequirements as $r) {
                            if (isset($data['price_'.$r->id])) {
                                $itemPrices[$r->id] = (float) $data['price_'.$r->id];
                            }
                        }

                        app(OrderMaterialFlowService::class)->triggerPoRequest(
                            order: $record,
                            supplier: $data['supplier'] ?? null,
                            notes: $data['notes'] ?? null,
                            actor: auth()->user(),
                            poNumber: $data['po_number'] ?? null,
                            itemPrices: $itemPrices
                        );

                        Notification::make()
                            ->title('PO Berhasil Diajukan!')
                            ->body("Purchase Order {$data['po_number']} berhasil dibuat dengan status SUBMITTED.")
                            ->success()
                            ->send();
                    }),

                // 3. Aksi: Konfirmasi Klien (Tanggungan Klien)
                Action::make('confirmClient')
                    ->label('Konfirmasi Klien')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->color('info')
                    ->visible(fn (Order $record): bool => static::canManageWarehouseFlow() && in_array($record->warehouse_material_status, [
                        OrderMaterialStatus::EMPTY,
                        OrderMaterialStatus::ITEM_REQUEST,
                    ], true) && $record->hasClientMaterials())
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Bahan dengan Klien')
                    ->modalDescription('Catat koordinasi pengiriman bahan/aksesoris yang disediakan oleh klien.')
                    ->form([
                        Textarea::make('notes')
                            ->label('Hasil Konfirmasi Klien')
                            ->placeholder('Misal: Klien konfirmasi kirim bahan tgl 15 Okt via ekspedisi...')
                            ->rows(3),
                    ])
                    ->action(function (Order $record, array $data): void {
                        app(OrderMaterialFlowService::class)->triggerClientConfirmation($record, $data['notes'] ?? null, auth()->user());

                        Notification::make()
                            ->title('Konfirmasi Klien Dicatat')
                            ->body('Status pesanan berhasil diperbarui ke tahap Konfirmasi Klien.')
                            ->success()
                            ->send();
                    }),

                // 4. Aksi: ACC Kedatangan Gudang (Ceklist Penerimaan Bahan Berbasis Pesanan)
                Action::make('accStockArrival')
                    ->label('ACC Kedatangan Gudang')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('warning')
                    ->modalWidth('4xl')
                    ->visible(fn (Order $record): bool => static::canManageWarehouseFlow() && in_array($record->warehouse_material_status, [
                        OrderMaterialStatus::ITEM_REQUEST,
                        OrderMaterialStatus::CLIENT_CONFIRMATION,
                        OrderMaterialStatus::PO_AND_CLIENT,
                        OrderMaterialStatus::DELIVERY_IN_TRANSIT,
                    ], true))
                    ->modalHeading(fn (Order $record): string => "ACC Kedatangan Bahan — Order #{$record->order_number}")
                    ->modalDescription('Ceklist bahan/aksesoris yang telah tiba fisiknya di gudang. Item yang dicentang otomatis diterima penuh sesuai kebutuhan.')
                    ->fillForm(function (Order $record): array {
                        if ($record->materialRequirements()->doesntExist()) {
                            app(OrderMaterialFlowService::class)->initializeRequirements($record);
                            $record->refresh();
                        }

                        $data = [
                            'common_batch' => '',
                            'common_source' => '',
                            'common_notes' => '',
                        ];

                        foreach ($record->materialRequirements as $r) {
                            $missing = $r->missingQuantity();
                            $data['check_'.$r->id] = $missing > 0;
                            $data['qty_'.$r->id] = $missing > 0 ? (float) $missing : 0;
                        }

                        return $data;
                    })
                    ->form(function (Order $record): array {
                        if ($record->materialRequirements()->doesntExist()) {
                            app(OrderMaterialFlowService::class)->initializeRequirements($record);
                            $record->refresh();
                        }

                        $components = [
                            Grid::make(3)->schema([
                                TextInput::make('common_batch')
                                    ->label('No. Surat Jalan / Lot / Batch')
                                    ->placeholder('Contoh: SJ-2026-001'),
                                TextInput::make('common_source')
                                    ->label('Sumber / Pengirim / Ekspedisi')
                                    ->placeholder('Nama Supplier / Klien / Ekspedisi'),
                                Textarea::make('common_notes')
                                    ->label('Catatan Penerimaan')
                                    ->placeholder('Kondisi kemasan, roll kain, dll...')
                                    ->rows(1),
                            ]),
                        ];

                        $itemRows = [];
                        foreach ($record->materialRequirements as $r) {
                            $missing = $r->missingQuantity();
                            $responsibilityLabel = $r->responsibility?->getLabel() ?? 'Bahan';
                            $typeLabel = match ($r->item_type?->value ?? '') {
                                'fabric' => 'Kain',
                                'accessory' => 'Aksesoris',
                                'trim' => 'Trim',
                                default => 'Item',
                            };

                            $infoText = sprintf(
                                '%s • %s | Kebutuhan: %s %s (Sudah tiba: %s %s, Kurang: %s %s)',
                                $typeLabel,
                                $responsibilityLabel,
                                number_format((float) $r->required_quantity, 2),
                                $r->uom,
                                number_format((float) $r->received_quantity, 2),
                                $r->uom,
                                number_format($missing, 2),
                                $r->uom
                            );

                            $itemRows[] = Grid::make(2)
                                ->schema([
                                    Checkbox::make('check_'.$r->id)
                                        ->label("ACC Diterima: {$r->item_name}")
                                        ->helperText($infoText)
                                        ->live(),
                                    TextInput::make('qty_'.$r->id)
                                        ->label("Jumlah Masuk ({$r->item_name})")
                                        ->numeric()
                                        ->minValue(0)
                                        ->suffix($r->uom ?: 'satuan')
                                        ->helperText("Kuantitas fisik yang diterima (default: {$missing} {$r->uom})")
                                        ->visible(fn (Get $get): bool => (bool) $get('check_'.$r->id))
                                        ->required(fn (Get $get): bool => (bool) $get('check_'.$r->id)),
                                ]);
                        }

                        $components[] = Section::make('Daftar Ceklist Bahan & Aksesoris Datang')
                            ->description('Centang item yang telah diterima fisiknya. Kuantitas otomatis terisi sesuai sisa kekurangan barang.')
                            ->schema($itemRows);

                        return $components;
                    })
                    ->action(function (Order $record, array $data): void {
                        $batch = $data['common_batch'] ?? null;
                        $source = $data['common_source'] ?? null;
                        $notes = $data['common_notes'] ?? null;
                        $flowService = app(OrderMaterialFlowService::class);
                        $recordedCount = 0;

                        foreach ($record->materialRequirements as $r) {
                            $isChecked = (bool) ($data['check_'.$r->id] ?? false);
                            $incoming = (float) ($data['qty_'.$r->id] ?? 0);

                            if ($isChecked && $incoming > 0) {
                                $flowService->recordStockReceipt(
                                    requirement: $r,
                                    quantity: $incoming,
                                    batch: $batch,
                                    source: $source,
                                    notes: $notes,
                                    actor: auth()->user()
                                );
                                $recordedCount++;
                            }
                        }

                        $record->refresh();
                        if ($record->warehouse_material_status === OrderMaterialStatus::STANDBY) {
                            Notification::make()
                                ->title('Bahan Lengkap — Status Berubah ke STANDBY!')
                                ->body('Seluruh kebutuhan bahan order telah terpenuhi. Bahan siap untuk proses cutting.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Penerimaan Bahan Dicatat')
                                ->body("Berhasil mencatat kedatangan untuk {$recordedCount} bahan/aksesoris. Status pengiriman diperbarui.")
                                ->info()
                                ->send();
                        }
                    }),

                // 5. Aksi: Mulai Cutting & Persiapan (Gudang)
                Action::make('startCutting')
                    ->label('Mulai Cutting')
                    ->icon(Heroicon::OutlinedScissors)
                    ->color('info')
                    ->visible(fn (Order $record): bool => static::canManageWarehouseFlow() && $record->warehouse_material_status === OrderMaterialStatus::STANDBY)
                    ->requiresConfirmation()
                    ->modalHeading('Mulai Proses Cutting Kain')
                    ->modalDescription('Pastikan kain dan pola telah siap di meja potong gudang.')
                    ->action(function (Order $record): void {
                        app(OrderMaterialFlowService::class)->startCuttingAndPreparation($record, auth()->user());

                        Notification::make()
                            ->title('Proses Cutting Dimulai')
                            ->body('Status pesanan bergeser ke Dalam Persiapan Pengiriman.')
                            ->info()
                            ->send();
                    }),

                // 6. Aksi: Kirim ke Produksi (Gudang)
                Action::make('dispatchToProduction')
                    ->label('Kirim ke Produksi')
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('primary')
                    ->visible(fn (Order $record): bool => static::canManageWarehouseFlow() && $record->warehouse_material_status === OrderMaterialStatus::PREPARING_DELIVERY)
                    ->requiresConfirmation()
                    ->modalHeading('Kirim Bundel Potongan & Aksesoris ke Lantai Produksi')
                    ->modalDescription('Seluruh bundel potongan kain dan kelengkapan aksesoris fisik akan diserah-terimakan ke bagian produksi.')
                    ->action(function (Order $record): void {
                        app(OrderMaterialFlowService::class)->dispatchToProduction($record, auth()->user());

                        Notification::make()
                            ->title('Bahan Sedang Dikirim')
                            ->body('Status pesanan telah berubah menjadi Sedang Dikirim. Menunggu ACC dari Kepala Produksi.')
                            ->success()
                            ->send();
                    }),

                // 7. Aksi: ACC Penerimaan Bahan oleh Kepala Produksi
                Action::make('accReceiveProduction')
                    ->label('ACC Penerimaan')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->modalWidth('4xl')
                    ->visible(function (Order $record): bool {
                        if ($record->warehouse_material_status !== OrderMaterialStatus::DISPATCHED) {
                            return false;
                        }

                        $user = auth()->user();

                        return $user?->hasRole(UserRole::PRODUCTION_LEAD)
                            || $user?->hasRole(UserRole::OWNER_EXECUTIVE);
                    })
                    ->modalHeading(fn (Order $record): string => "ACC Penerimaan Bahan Produksi — Order #{$record->order_number}")
                    ->modalDescription('Verifikasi kesesuaian fisik bundel potongan kain dan kelengkapan aksesoris yang tiba di lantai produksi. Jika terdapat selisih jumlah, pilih "Tidak Sesuai" dan masukkan angka fisik riil.')
                    ->fillForm(function (Order $record): array {
                        $data = [
                            'notes' => '',
                        ];

                        foreach ($record->materialRequirements as $r) {
                            $deliveredQty = (float) ($r->received_quantity > 0 ? $r->received_quantity : $r->required_quantity);
                            $data['is_match_'.$r->id] = '1';
                            $data['actual_qty_'.$r->id] = $deliveredQty;
                        }

                        return $data;
                    })
                    ->form(function (Order $record): array {
                        $components = [
                            Grid::make(1)->schema([
                                Textarea::make('notes')
                                    ->label('Catatan Serah Terima / Kondisi Fisik di Lantai Produksi')
                                    ->placeholder('Misal: Bundel potongan kain lengkap, kondisi rapi, dll...')
                                    ->rows(2),
                            ]),
                        ];

                        $itemRows = [];
                        foreach ($record->materialRequirements as $r) {
                            $deliveredQty = (float) ($r->received_quantity > 0 ? $r->received_quantity : $r->required_quantity);
                            $responsibilityLabel = $r->responsibility?->getLabel() ?? 'Bahan';
                            $typeLabel = match ($r->item_type?->value ?? '') {
                                'fabric' => 'Kain Terpotong',
                                'accessory' => 'Aksesoris',
                                'trim' => 'Trim',
                                default => 'Item',
                            };

                            $infoText = sprintf(
                                '%s • %s | Data Kiriman Gudang: %s %s (Target Kebutuhan Order: %s %s)',
                                $typeLabel,
                                $responsibilityLabel,
                                number_format($deliveredQty, 2),
                                $r->uom,
                                number_format((float) $r->required_quantity, 2),
                                $r->uom
                            );

                            $itemRows[] = Section::make($r->item_name)
                                ->description($infoText)
                                ->compact()
                                ->schema([
                                    Grid::make(2)->schema([
                                        Radio::make('is_match_'.$r->id)
                                            ->label('Status Kesesuaian Jumlah')
                                            ->options([
                                                '1' => "Sesuai Data Kiriman ({$deliveredQty} {$r->uom})",
                                                '0' => 'Tidak Sesuai / Ada Selisih',
                                            ])
                                            ->default('1')
                                            ->inline()
                                            ->live(),
                                        TextInput::make('actual_qty_'.$r->id)
                                            ->label("Input Jumlah Fisik Sebenarnya ({$r->item_name})")
                                            ->numeric()
                                            ->minValue(0)
                                            ->default($deliveredQty)
                                            ->suffix($r->uom ?: 'satuan')
                                            ->helperText("Masukkan jumlah fisik riil yang diterima lantai produksi bila berbeda dari {$deliveredQty} {$r->uom}")
                                            ->visible(fn (Get $get): bool => $get('is_match_'.$r->id) === '0' || $get('is_match_'.$r->id) === 0)
                                            ->required(fn (Get $get): bool => $get('is_match_'.$r->id) === '0' || $get('is_match_'.$r->id) === 0),
                                    ]),
                                ]);
                        }

                        $components[] = Section::make('Daftar Verifikasi Fisik Bahan & Aksesoris')
                            ->description('Kepala Produksi wajib memeriksa fisik tiap item. Bila ada selisih, pilih "Tidak Sesuai" dan inputkan angka fisik riil yang diterima.')
                            ->schema($itemRows);

                        return $components;
                    })
                    ->action(function (Order $record, array $data): void {
                        $discrepancies = [];

                        foreach ($record->materialRequirements as $r) {
                            $isMatch = (string) ($data['is_match_'.$r->id] ?? '1') === '1';
                            $deliveredQty = (float) ($r->received_quantity > 0 ? $r->received_quantity : $r->required_quantity);
                            $actualQty = $isMatch ? $deliveredQty : (float) ($data['actual_qty_'.$r->id] ?? $deliveredQty);

                            if (! $isMatch && abs($actualQty - $deliveredQty) > 0.001) {
                                $diff = round($actualQty - $deliveredQty, 2);
                                $diffStr = $diff > 0 ? "+{$diff}" : "{$diff}";
                                $discrepancies[] = "• {$r->item_name}: Surat Jalan {$deliveredQty} {$r->uom}, Fisik Diterima {$actualQty} {$r->uom} (Selisih: {$diffStr} {$r->uom})";

                                $r->received_quantity = (string) round($actualQty, 2);
                                $r->save();
                            }
                        }

                        $finalNotes = $data['notes'] ?? '';
                        if (! empty($discrepancies)) {
                            $discrepancyBlock = "Catatan Selisih Fisik Penerimaan Produksi:\n".implode("\n", $discrepancies);
                            $finalNotes = trim($finalNotes.($finalNotes ? "\n\n" : '').$discrepancyBlock);
                        }

                        app(OrderMaterialFlowService::class)->confirmReceivedByProduction(
                            order: $record,
                            actor: auth()->user(),
                            notes: $finalNotes ?: null
                        );

                        if (! empty($discrepancies)) {
                            Notification::make()
                                ->title('Bahan Diterima (Ada Catatan Selisih)')
                                ->body('Penerimaan bahan berhasil disahkan dengan catatan '.count($discrepancies).' item berbeda dari data surat jalan. Selisih telah dicatat di sistem.')
                                ->warning()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Bahan Resmi Diterima Produksi')
                                ->body('Seluruh bahan kain terpotong & aksesoris pesanan telah diverifikasi sesuai dan resmi diterima oleh Bagian Produksi. Perakitan dan jahit siap dimulai.')
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Memeriksa apakah pengguna yang aktif memiliki wewenang mengelola alur kerja gudang & cutting.
     */
    protected static function canManageWarehouseFlow(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->hasRole(UserRole::WAREHOUSE)
            || $user?->hasRole(UserRole::OWNER_EXECUTIVE);
    }
}
