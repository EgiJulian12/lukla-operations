<?php

namespace App\Services\Inventory;

use App\Enums\ComponentMaterialStatus;
use App\Enums\DispatchStatus;
use App\Enums\InventoryState;
use App\Enums\MaterialItemType;
use App\Enums\MaterialResponsibility;
use App\Enums\MovementType;
use App\Enums\OrderMaterialStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\WorkScheme;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialDispatch;
use App\Models\Order;
use App\Models\OrderMaterialRequirement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service alur pergerakan bahan dan cutting berbasis pesanan (Order-Centric Warehouse Hub).
 */
class OrderMaterialFlowService
{
    public function __construct(
        protected InventoryService $inventoryService,
    ) {}

    /**
     * Inisialisasi daftar kebutuhan bahan & aksesoris awal berdasarkan skema maklon order.
     *
     * @return Collection<int, OrderMaterialRequirement>
     */
    public function initializeRequirements(Order $order): Collection
    {
        return DB::transaction(function () use ($order): Collection {
            if ($order->materialRequirements()->exists()) {
                return $order->materialRequirements()->get();
            }

            $scheme = $order->work_scheme;
            $createdRequirements = collect();

            // Aturan tanggungan berdasarkan skema maklon
            $fabricResponsibility = match ($scheme) {
                WorkScheme::CMT, WorkScheme::SEMI_CMT => MaterialResponsibility::CLIENT,
                WorkScheme::FULL_PACKAGE => MaterialResponsibility::COMPANY,
            };

            $accessoryResponsibility = match ($scheme) {
                WorkScheme::CMT => MaterialResponsibility::CLIENT,
                WorkScheme::SEMI_CMT, WorkScheme::FULL_PACKAGE => MaterialResponsibility::COMPANY,
            };

            // 1. Cek apakah ada sampleRevision dan spesifikasi material_usage pada order
            $order->loadMissing(['sampleRevision.sample.article.components', 'sample.approvedRevision', 'client']);
            $sampleRevision = $order->sampleRevision ?? $order->sample?->approvedRevision;
            $materialUsage = $sampleRevision?->material_usage;

            if (! empty($materialUsage) && is_array($materialUsage)) {
                foreach ($materialUsage as $rawKey => $usagePerUnit) {
                    $keyName = ucwords(str_replace(['_', '-'], ' ', (string) $rawKey));
                    $isFabric = str_contains(strtolower($keyName), 'kain')
                        || str_contains(strtolower($keyName), 'fabric')
                        || str_contains(strtolower($keyName), 'fleece')
                        || str_contains(strtolower($keyName), 'cotton')
                        || str_contains(strtolower($keyName), 'combed');

                    $itemType = $isFabric ? MaterialItemType::FABRIC : MaterialItemType::ACCESSORY;
                    $responsibility = $isFabric ? $fabricResponsibility : $accessoryResponsibility;

                    $usageStr = trim((string) $usagePerUnit);
                    $numericVal = 1.0;
                    $detectedUom = null;

                    if (preg_match('/^([0-9]+(?:[\.,][0-9]+)?)\s*(.*)$/i', $usageStr, $matches)) {
                        $numericVal = (float) str_replace(',', '.', $matches[1]);
                        $rawUom = strtolower(trim($matches[2] ?? ''));

                        if (str_contains($rawUom, 'meter') || $rawUom === 'm') {
                            $detectedUom = 'meter';
                        } elseif (str_contains($rawUom, 'yard') || $rawUom === 'yd') {
                            $detectedUom = 'yard';
                        } elseif (str_contains($rawUom, 'pcs') || str_contains($rawUom, 'buah') || str_contains($rawUom, 'biji')) {
                            $detectedUom = 'pcs';
                        } elseif (str_contains($rawUom, 'roll')) {
                            $detectedUom = 'roll';
                        } elseif (str_contains($rawUom, 'spool')) {
                            $detectedUom = 'spool';
                        } elseif (str_contains($rawUom, 'set')) {
                            $detectedUom = 'set';
                        } elseif (str_contains($rawUom, 'lusin') || str_contains($rawUom, 'dz')) {
                            $detectedUom = 'lusin';
                        } elseif (! empty($rawUom)) {
                            $detectedUom = Str::limit($rawUom, 15, '');
                        }
                    } elseif (is_numeric($usagePerUnit)) {
                        $numericVal = (float) $usagePerUnit;
                    }

                    $uom = $detectedUom ?? ($isFabric ? 'meter' : 'pcs');
                    $requiredQty = round($numericVal * (float) $order->quantity, 2);

                    // Cari material yang cocok jika ada
                    $matchedMaterial = Material::query()
                        ->where(function ($q) use ($order) {
                            $q->where('client_id', $order->client_id)
                                ->orWhereNull('client_id');
                        })
                        ->where(function ($q) use ($keyName) {
                            $q->where('name', 'like', "%{$keyName}%")
                                ->orWhere('code', 'like', "%{$keyName}%");
                        })
                        ->first();

                    // Jika belum terdaftar di master data bahan gudang, otomatis daftarkan
                    if (! $matchedMaterial) {
                        $cleanSlug = strtoupper(Str::slug(Str::limit($keyName, 8, '')));
                        $code = 'MAT-'.($cleanSlug ?: 'ITEM').'-'.rand(100, 999);
                        while (Material::where('code', $code)->exists()) {
                            $code = 'MAT-'.strtoupper(Str::random(6));
                        }

                        $matchedMaterial = Material::create([
                            'client_id' => $order->client_id,
                            'code' => $code,
                            'name' => $keyName,
                            'category' => $itemType === MaterialItemType::FABRIC ? 'fabric' : 'accessory',
                            'uom' => $uom,
                        ]);
                    }

                    $req = OrderMaterialRequirement::create([
                        'order_id' => $order->getKey(),
                        'material_id' => $matchedMaterial->getKey(),
                        'item_name' => $matchedMaterial->name,
                        'item_type' => $itemType,
                        'responsibility' => $responsibility,
                        'status' => ComponentMaterialStatus::EMPTY,
                        'required_quantity' => $requiredQty,
                        'received_quantity' => 0,
                        'uom' => $matchedMaterial->uom ?: $uom,
                    ]);
                    $createdRequirements->push($req);
                }
            }

            // 2. Jika order tanpa sample atau tanpa material_usage, ambil material terdaftar milik klien
            if ($createdRequirements->isEmpty()) {
                $clientMaterials = Material::query()
                    ->where('client_id', $order->client_id)
                    ->get();

                if ($clientMaterials->isNotEmpty()) {
                    foreach ($clientMaterials as $mat) {
                        $isFabric = $mat->category === 'fabric' || str_contains(strtolower($mat->name), 'kain');
                        $createdRequirements->push(OrderMaterialRequirement::create([
                            'order_id' => $order->getKey(),
                            'material_id' => $mat->id,
                            'item_name' => $mat->name,
                            'item_type' => $isFabric ? MaterialItemType::FABRIC : MaterialItemType::ACCESSORY,
                            'responsibility' => $isFabric ? $fabricResponsibility : $accessoryResponsibility,
                            'status' => ComponentMaterialStatus::EMPTY,
                            'required_quantity' => round($order->quantity * ($isFabric ? 1.5 : 1), 2),
                            'received_quantity' => 0,
                            'uom' => $mat->uom,
                        ]));
                    }
                }
            }

            // 4. Fallback jika klien belum mendaftarkan bahan: buat 1 item kain & 1 item aksesoris dinamis
            if ($createdRequirements->isEmpty()) {
                $createdRequirements->push(OrderMaterialRequirement::create([
                    'order_id' => $order->getKey(),
                    'material_id' => null,
                    'item_name' => 'Kain Utama Order #'.$order->order_number,
                    'item_type' => MaterialItemType::FABRIC,
                    'responsibility' => $fabricResponsibility,
                    'status' => ComponentMaterialStatus::EMPTY,
                    'required_quantity' => round($order->quantity * 1.5, 2),
                    'received_quantity' => 0,
                    'uom' => 'meter',
                ]));

                $createdRequirements->push(OrderMaterialRequirement::create([
                    'order_id' => $order->getKey(),
                    'material_id' => null,
                    'item_name' => 'Aksesoris & Packaging Order #'.$order->order_number,
                    'item_type' => MaterialItemType::ACCESSORY,
                    'responsibility' => $accessoryResponsibility,
                    'status' => ComponentMaterialStatus::EMPTY,
                    'required_quantity' => (float) $order->quantity,
                    'received_quantity' => 0,
                    'uom' => 'set',
                ]));
            }

            return $createdRequirements;
        });
    }

    /**
     * Sinkronkan ulang kebutuhan bahan order saat kuantitas atau spesifikasi sample diperbarui,
     * selama belum ada bahan yang berstatus diterima fisik di gudang.
     *
     * @return Collection<int, OrderMaterialRequirement>
     */
    public function syncRequirementsForOrder(Order $order): Collection
    {
        return DB::transaction(function () use ($order): Collection {
            $hasReceived = $order->materialRequirements()->where('received_quantity', '>', 0)->exists();

            if (! $hasReceived) {
                $order->materialRequirements()->delete();
            }

            return $this->initializeRequirements($order);
        });
    }

    /**
     * Memicu tindakan Pengajuan PO / Bahan oleh Gudang (untuk tanggungan perusahaan).
     */
    public function triggerPoRequest(
        Order $order,
        ?string $supplier = null,
        ?string $notes = null,
        ?User $actor = null,
        ?string $poNumber = null,
        ?array $itemPrices = []
    ): Order {
        return DB::transaction(function () use ($order, $supplier, $notes, $actor, $poNumber, $itemPrices): Order {
            $now = Carbon::now();
            $order->po_requested_at = $now;

            // Jika konfirmasi klien sudah ada sebelumnya -> status gabungan
            if ($order->client_confirmed_at !== null) {
                $order->warehouse_material_status = OrderMaterialStatus::PO_AND_CLIENT;
            } else {
                $order->warehouse_material_status = OrderMaterialStatus::ITEM_REQUEST;
            }

            if ($notes) {
                $order->notes = trim(($order->notes ? $order->notes."\n" : '')."[PO Disubmit {$now->format('d/m/Y H:i')}]: ".$notes);
            }

            $order->save();

            // Ambil item tanggungan perusahaan
            $companyRequirements = $order->materialRequirements()
                ->whereIn('responsibility', [MaterialResponsibility::COMPANY, MaterialResponsibility::PARTIAL])
                ->get();

            // Buat record PurchaseOrder resmi dengan status submitted
            $generatedPoNumber = $poNumber ?: ('PO-'.$now->format('Y-d-H-i'));
            // Cek keunikan po_number jika sudah ada yang sama pada menit yang sama
            if (PurchaseOrder::where('po_number', $generatedPoNumber)->exists()) {
                $generatedPoNumber .= '-'.$order->id;
            }

            $po = PurchaseOrder::create([
                'order_id' => $order->id,
                'client_id' => $order->client_id,
                'po_number' => $generatedPoNumber,
                'supplier' => $supplier ?: 'Supplier Order #'.$order->order_number,
                'status' => PurchaseOrderStatus::SUBMITTED,
                'ordered_at' => $now->toDateString(),
                'requested_by' => $actor?->id ?? auth()->id(),
                'notes' => $notes,
            ]);

            // Isi purchase_order_items berdasarkan barang yang dimiliki order
            foreach ($companyRequirements as $req) {
                $missing = $req->missingQuantity();
                $qtyToOrder = $missing > 0 ? (float) $missing : (float) $req->required_quantity;

                // Jika requirement belum punya material_id di DB, carikan atau buatkan material referensi
                $materialId = $req->material_id;
                if (! $materialId) {
                    $materialCode = 'MAT-'.strtoupper(Str::random(6));
                    $material = Material::firstOrCreate(
                        [
                            'name' => $req->item_name,
                            'client_id' => $order->client_id,
                        ],
                        [
                            'code' => $materialCode,
                            'category' => $req->item_type?->value === 'fabric' ? 'fabric' : 'accessory',
                            'uom' => $req->uom ?: 'pcs',
                        ]
                    );
                    $materialId = $material->id;
                    $req->update(['material_id' => $materialId]);
                }

                $price = isset($itemPrices[$req->id]) ? (float) $itemPrices[$req->id] : 0;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'material_id' => $materialId,
                    'quantity' => $qtyToOrder,
                    'unit_price' => $price,
                ]);
            }

            // Update status item yang ditanggung perusahaan
            $order->materialRequirements()
                ->whereIn('responsibility', [MaterialResponsibility::COMPANY, MaterialResponsibility::PARTIAL])
                ->where('status', ComponentMaterialStatus::EMPTY->value)
                ->update(['status' => ComponentMaterialStatus::ITEM_REQUEST->value]);

            return $order->fresh(['materialRequirements']);
        });
    }

    /**
     * Memicu tindakan Konfirmasi Klien oleh Gudang (untuk tanggungan klien).
     */
    public function triggerClientConfirmation(Order $order, ?string $notes = null, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $notes): Order {
            $now = Carbon::now();
            $order->client_confirmed_at = $now;

            // Jika PO sudah diajukan sebelumnya -> status gabungan
            if ($order->po_requested_at !== null) {
                $order->warehouse_material_status = OrderMaterialStatus::PO_AND_CLIENT;
            } else {
                $order->warehouse_material_status = OrderMaterialStatus::CLIENT_CONFIRMATION;
            }

            if ($notes) {
                $order->notes = trim(($order->notes ? $order->notes."\n" : '')."[Konfirmasi Klien {$now->format('d/m/Y H:i')}]: ".$notes);
            }

            $order->save();

            // Update status item yang ditanggung klien
            $order->materialRequirements()
                ->whereIn('responsibility', [MaterialResponsibility::CLIENT, MaterialResponsibility::PARTIAL])
                ->where('status', ComponentMaterialStatus::EMPTY->value)
                ->update(['status' => ComponentMaterialStatus::WAITING_CLIENT->value]);

            return $order->fresh(['materialRequirements']);
        });
    }

    /**
     * Mencatat penerimaan stok bahan masuk secara manual per komponen/bahan.
     * Jika total kuantitas diterima sudah memenuhi seluruh kebutuhan order -> status otomatis ke STANDBY.
     */
    public function recordStockReceipt(
        OrderMaterialRequirement $requirement,
        float $quantity,
        ?string $batch = null,
        ?string $source = null,
        ?string $notes = null,
        ?User $actor = null,
    ): OrderMaterialRequirement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Kuantitas stok masuk harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($requirement, $quantity, $batch, $source, $notes, $actor): OrderMaterialRequirement {
            $order = $requirement->order;
            $newReceived = (float) $requirement->received_quantity + $quantity;

            $requirement->received_quantity = (string) round($newReceived, 2);
            if ($batch) {
                $requirement->tracking_number_or_batch = $batch;
            }
            if ($source) {
                $requirement->supplier_or_source = $source;
            }
            if ($notes) {
                $requirement->notes = trim(($requirement->notes ? $requirement->notes."\n" : '').$notes);
            }
            if ($actor) {
                $requirement->updated_by = $actor->getKey();
            }

            // Update status per komponen
            if ($newReceived >= (float) $requirement->required_quantity) {
                $requirement->status = ComponentMaterialStatus::STANDBY;
            } else {
                $requirement->status = ComponentMaterialStatus::DELIVERY_IN_TRANSIT;
            }

            // Pastikan Material terdaftar, jika belum ada otomatis dibuatkan master Material untuk pesanan ini
            $material = null;
            if ($requirement->material_id) {
                $material = Material::find($requirement->material_id);
            }

            if (! $material) {
                $category = match ($requirement->item_type) {
                    MaterialItemType::FABRIC => 'fabric',
                    MaterialItemType::ACCESSORY => 'accessory',
                    MaterialItemType::TRIM => 'trim',
                    MaterialItemType::PACKAGING => 'packaging',
                    default => 'other',
                };

                $prefix = strtoupper(substr($category, 0, 3));
                $code = sprintf('MAT-%s-%04d-%s', $prefix, $order->client_id, strtoupper(bin2hex(random_bytes(2))));

                $material = Material::create([
                    'client_id' => $order->client_id,
                    'code' => $code,
                    'name' => $requirement->item_name,
                    'uom' => $requirement->uom ?: 'meter',
                    'category' => $category,
                ]);

                $requirement->material_id = $material->getKey();
            }

            // Catat fisik barang masuk ke Stok Gudang (inventory_items & inventory_movements)
            $inventoryItem = $this->inventoryService->receive(
                order: $order,
                material: $material,
                quantity: $quantity,
                batch: $batch ?? ('BATCH-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(2)))),
                location: 'Gudang Utama Lukla',
                state: InventoryState::RAW,
                reference: sprintf('Penerimaan Alur Bahan #%s: %s', $order->order_number, $requirement->item_name),
            );

            $requirement->inventory_item_id = $inventoryItem->getKey();
            $requirement->save();

            // Pengecekan otomatis status order (Level 1)
            $order->refresh();
            if ($order->isAllMaterialsFulfilled()) {
                $order->warehouse_material_status = OrderMaterialStatus::STANDBY;
                $order->save();
            } else {
                // Jika belum standby tapi ada stok masuk dan sebelumnya masih kosong/pengajuan, ubah ke pengiriman_barang
                if (in_array($order->warehouse_material_status, [
                    OrderMaterialStatus::EMPTY,
                    OrderMaterialStatus::ITEM_REQUEST,
                    OrderMaterialStatus::CLIENT_CONFIRMATION,
                    OrderMaterialStatus::PO_AND_CLIENT,
                ], true)) {
                    $order->warehouse_material_status = OrderMaterialStatus::DELIVERY_IN_TRANSIT;
                    $order->save();
                }
            }

            return $requirement->fresh();
        });
    }

    /**
     * Gudang memulai proses cutting dan persiapan bundel siap jahit.
     * Hanya dapat dilakukan saat status order sudah STANDBY.
     */
    public function startCuttingAndPreparation(Order $order, ?User $actor = null): Order
    {
        if ($order->warehouse_material_status !== OrderMaterialStatus::STANDBY) {
            throw new InvalidArgumentException('Proses cutting hanya dapat dimulai bila seluruh bahan sudah STANDBY.');
        }

        return DB::transaction(function () use ($order): Order {
            $order->warehouse_material_status = OrderMaterialStatus::PREPARING_DELIVERY;
            $order->cutting_started_at = Carbon::now();
            $order->save();

            // Bahan kain masuk ke status dalam_cutting
            $order->materialRequirements()
                ->where('item_type', MaterialItemType::FABRIC->value)
                ->update(['status' => ComponentMaterialStatus::IN_CUTTING->value]);

            // Rekam mutasi pergerakan stok: Pengeluaran seluruh batch kain ke meja cutting
            $fabricRequirements = $order->materialRequirements()
                ->where('item_type', MaterialItemType::FABRIC->value)
                ->get();

            foreach ($fabricRequirements as $req) {
                foreach ($req->getInventoryItems() as $batchItem) {
                    // Idempotency guard: lewati jika log ISSUE_TO_CUTTING sudah ada untuk batch ini
                    $alreadyIssued = $batchItem->movements()
                        ->where('movement_type', MovementType::ISSUE_TO_CUTTING->value)
                        ->exists();

                    if (! $alreadyIssued && (float) $batchItem->quantity > 0) {
                        $this->inventoryService->recordMovement(
                            item: $batchItem,
                            type: MovementType::ISSUE_TO_CUTTING,
                            quantity: (float) $batchItem->quantity,
                            destination: $order,
                            reference: sprintf('Proses Cutting #%s', $order->order_number),
                            notes: sprintf('Pengeluaran kain ke meja potong untuk %s (Batch: %s)', $req->item_name, $batchItem->batch ?? '-'),
                        );
                    }
                }
            }

            return $order->fresh(['materialRequirements']);
        });
    }

    /**
     * Gudang mengirimkan paket potongan siap jahit & aksesoris ke bagian produksi.
     */
    public function dispatchToProduction(Order $order, ?User $actor = null): Order
    {
        if ($order->warehouse_material_status !== OrderMaterialStatus::PREPARING_DELIVERY) {
            throw new InvalidArgumentException('Pengiriman ke produksi hanya dapat dilakukan setelah tahap persiapan cutting.');
        }

        return DB::transaction(function () use ($order, $actor): Order {
            $order->warehouse_material_status = OrderMaterialStatus::DISPATCHED;
            $order->dispatched_to_production_at = Carbon::now();
            $order->save();

            // Seluruh komponen & aksesoris ditandai sedang dikirim
            $order->materialRequirements()->update([
                'status' => ComponentMaterialStatus::DISPATCHED->value,
            ]);

            // Sinkronisasi otomatis ke Pengantaran Bahan ke Produksi (MaterialDispatch)
            $actorId = $actor?->getKey() ?? auth()->id();
            $order->refresh();

            foreach ($order->materialRequirements as $requirement) {
                $batchItems = $requirement->getInventoryItems();

                if ($batchItems->isEmpty()) {
                    continue;
                }

                foreach ($batchItems as $batchItem) {
                    // Hitung kuantitas yang masuk pada batch ini berdasarkan log RECEIPT
                    $batchReceivedQty = (float) $batchItem->movements()
                        ->where('movement_type', MovementType::RECEIPT->value)
                        ->sum('quantity');

                    if ($batchReceivedQty <= 0) {
                        $batchReceivedQty = (float) $batchItem->quantity;
                    }

                    // Cek apakah sudah ada dispatch aktif untuk batch-item ini
                    $existing = MaterialDispatch::where('order_id', $order->getKey())
                        ->where('inventory_item_id', $batchItem->getKey())
                        ->whereNotIn('status', [DispatchStatus::REJECTED, DispatchStatus::COMPLETED])
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'status' => DispatchStatus::DISPATCHED,
                            'dispatched_by' => $actorId,
                            'dispatched_at' => Carbon::now(),
                        ]);
                    } else {
                        // Nomor dispatch unik per batch — hindari tabrakan jika dibuat di detik yang sama
                        $dispatchCount = MaterialDispatch::whereDate('created_at', today())->count() + 1;
                        do {
                            $dispatchNumber = sprintf('DSP-%s-%04d', date('Ymd'), $dispatchCount++);
                        } while (MaterialDispatch::where('dispatch_number', $dispatchNumber)->exists());

                        MaterialDispatch::create([
                            'dispatch_number' => $dispatchNumber,
                            'client_id' => $order->client_id,
                            'order_id' => $order->getKey(),
                            'inventory_item_id' => $batchItem->getKey(),
                            'material_id' => $batchItem->material_id,
                            'quantity' => $batchReceivedQty,
                            'uom' => $batchItem->uom ?: ($requirement->uom ?: 'satuan'),
                            'status' => DispatchStatus::DISPATCHED,
                            'notes' => sprintf('Pengiriman otomatis dari Alur Bahan Pesanan #%s (%s - %s)', $order->order_number, $requirement->item_name, $batchItem->batch ?? 'Batch'),
                            'dispatched_by' => $actorId,
                            'dispatched_at' => Carbon::now(),
                        ]);
                    }

                    // Rekam mutasi TRANSFER per batch — idempotency guard
                    $alreadyTransferred = InventoryMovement::where('inventory_item_id', $batchItem->getKey())
                        ->where('order_id', $order->getKey())
                        ->where('movement_type', MovementType::TRANSFER->value)
                        ->exists();

                    if (! $alreadyTransferred && $batchReceivedQty > 0) {
                        if ((float) $batchItem->quantity > 0) {
                            // Stok fisik masih ada (aksesoris / item non-kain) — kurangi via recordMovement
                            $qtyToDeduct = min((float) $batchItem->quantity, $batchReceivedQty);
                            $this->inventoryService->recordMovement(
                                item: $batchItem,
                                type: MovementType::TRANSFER,
                                quantity: $qtyToDeduct,
                                destination: $order,
                                reference: sprintf('Pengiriman ke Produksi #%s', $order->order_number),
                                notes: sprintf('Pengiriman bahan/aksesoris ke bagian produksi (%s - %s)', $requirement->item_name, $batchItem->batch ?? '-'),
                            );
                        } else {
                            // Stok = 0 karena sudah di-ISSUE_TO_CUTTING (kain) — catat TRANSFER serah-terima
                            InventoryMovement::create([
                                'client_id' => $batchItem->client_id,
                                'order_id' => $order->getKey(),
                                'inventory_item_id' => $batchItem->getKey(),
                                'movement_type' => MovementType::TRANSFER,
                                'quantity' => -$batchReceivedQty,
                                'reference' => sprintf('Pengiriman ke Produksi #%s', $order->order_number),
                                'notes' => sprintf('Transfer potongan bahan keluar cutting ke bagian produksi (%s - %s)', $requirement->item_name, $batchItem->batch ?? '-'),
                                'created_by' => auth()->id(),
                                'recorded_at' => Carbon::now(),
                            ]);
                        }
                    }
                }
            }

            return $order->fresh(['materialRequirements']);
        });
    }

    /**
     * Kepala Produksi melakukan ACC penerimaan bahan siap guna di lantai produksi.
     */
    public function confirmReceivedByProduction(Order $order, ?User $actor = null, ?string $notes = null): Order
    {
        if ($order->warehouse_material_status !== OrderMaterialStatus::DISPATCHED) {
            throw new InvalidArgumentException('ACC penerimaan hanya dapat dilakukan pada kiriman yang berstatus Sedang Dikirim.');
        }

        return DB::transaction(function () use ($order, $actor, $notes): Order {
            $now = Carbon::now();
            $order->warehouse_material_status = OrderMaterialStatus::RECEIVED_BY_PRODUCTION;
            $order->received_by_production_at = $now;

            if ($notes) {
                $order->notes = trim(($order->notes ? $order->notes."\n" : '')."[ACC Produksi {$now->format('d/m/Y H:i')}]: ".$notes);
            }

            $order->save();

            // Seluruh komponen & aksesoris resmi diterima produksi
            $order->materialRequirements()->update([
                'status' => ComponentMaterialStatus::RECEIVED->value,
            ]);

            // Sinkronisasi status Pengantaran Bahan ke Produksi (MaterialDispatch) menjadi RECEIVED
            $receiverId = $actor?->getKey() ?? auth()->id();

            $dispatches = MaterialDispatch::where('order_id', $order->getKey())
                ->whereIn('status', [DispatchStatus::DISPATCHED, DispatchStatus::PENDING_DELIVERY])
                ->get();

            foreach ($dispatches as $dispatch) {
                $dispatchNotes = $dispatch->notes;
                if ($notes) {
                    $dispatchNotes = trim(($dispatchNotes ? $dispatchNotes."\n" : '').'[Catatan Penerimaan Produksi]: '.$notes);
                }

                $dispatch->update([
                    'status' => DispatchStatus::RECEIVED,
                    'received_by' => $receiverId,
                    'received_at' => $now,
                    'notes' => $dispatchNotes,
                ]);

                // Update status item stok fisik menjadi WIP (sedang dalam proses di lantai produksi)
                if ($dispatch->inventoryItem && $dispatch->inventoryItem->state === InventoryState::RAW) {
                    $dispatch->inventoryItem->update(['state' => InventoryState::WIP]);
                }
            }

            return $order->fresh(['materialRequirements']);
        });
    }
}
