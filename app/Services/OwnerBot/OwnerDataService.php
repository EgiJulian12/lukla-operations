<?php

namespace App\Services\OwnerBot;

use App\Enums\DispatchStatus;
use App\Enums\InventoryState;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\MaterialDispatch;
use App\Models\Order;
use App\Services\Ai\RagService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Service to retrieve operational, financial, and inventory data
 * specifically for the Owner role without tenant restrictions.
 */
class OwnerDataService
{
    public function __construct(
        private readonly RagService $ragService
    ) {}

    /**
     * Search documentation, SRS, SOP, Problem Statements, and business rules.
     *
     * @return array<string, mixed>
     */
    public function searchKnowledgeBase(string $query): array
    {
        $cleanQuery = trim($query);

        if (! $this->ragService) {
            return [
                'found' => false,
                'message' => 'Layanan RAG Knowledge Base belum aktif.',
                'documents' => [],
            ];
        }

        try {
            $chunks = $this->ragService->searchDocuments($cleanQuery, 4);

            if (empty($chunks)) {
                return [
                    'found' => false,
                    'message' => "Tidak ditemukan dokumen yang cocok dengan kata kunci '{$cleanQuery}'.",
                    'documents' => [],
                ];
            }

            return [
                'found' => true,
                'query' => $cleanQuery,
                'total_found' => count($chunks),
                'documents' => array_map(fn ($c) => [
                    'source' => $c['source'],
                    'excerpt' => $c['content'],
                ], $chunks),
            ];
        } catch (\Throwable $e) {
            return [
                'found' => false,
                'message' => 'Gagal membaca dokumen RAG: '.$e->getMessage(),
                'documents' => [],
            ];
        }
    }

    /**
     * Track what an inventory item / material is doing right now.
     *
     * @param  string|int  $identifier  Item ID, Tag ID (TAG-CLI1-ORD001-00005), or batch
     * @return array<string, mixed>
     */
    public function trackInventoryItem(string|int $identifier): array
    {
        $cleanId = trim((string) $identifier);

        $item = null;

        // 1. Direct ID match
        if (is_numeric($cleanId)) {
            $item = InventoryItem::with([
                'material',
                'client',
                'order.client',
                'order.article',
                'dispatches' => fn ($q) => $q->latest()->limit(5),
                'movements' => fn ($q) => $q->latest()->limit(5),
            ])->find((int) $cleanId);
        }

        // 2. Tag ID match (e.g. TAG-CLI1-ORD001-00042)
        if (! $item && preg_match('/TAG-[A-Za-z0-9]+-[A-Za-z0-9]+-(\d+)/i', $cleanId, $matches)) {
            $extractedId = (int) $matches[1];
            $item = InventoryItem::with([
                'material',
                'client',
                'order.client',
                'order.article',
                'dispatches' => fn ($q) => $q->latest()->limit(5),
                'movements' => fn ($q) => $q->latest()->limit(5),
            ])->find($extractedId);
        }

        // 3. Batch or fuzzy match
        if (! $item) {
            $item = InventoryItem::with([
                'material',
                'client',
                'order.client',
                'order.article',
                'dispatches' => fn ($q) => $q->latest()->limit(5),
                'movements' => fn ($q) => $q->latest()->limit(5),
            ])
                ->where('batch', 'ilike', "%{$cleanId}%")
                ->orWhereHas('material', fn (Builder $q) => $q->where('name', 'ilike', "%{$cleanId}%")->orWhere('code', 'ilike', "%{$cleanId}%"))
                ->first();
        }

        if (! $item) {
            return [
                'found' => false,
                'identifier' => $cleanId,
                'message' => "Barang / item dengan identitas '{$cleanId}' tidak ditemukan di database.",
            ];
        }

        /** @var MaterialDispatch|null $latestDispatch */
        $latestDispatch = $item->dispatches->first();

        // Determine current activity in human language
        $activityDescription = $this->resolveItemActivity($item, $latestDispatch);

        $recentMovements = $item->movements->map(fn ($m) => [
            'type' => $m->movement_type?->getLabel() ?? $m->movement_type?->value,
            'quantity' => (float) $m->quantity,
            'reference' => $m->reference,
            'notes' => $m->notes,
            'recorded_at' => $m->recorded_at?->format('d/m/Y H:i') ?? $m->created_at?->format('d/m/Y H:i'),
        ])->toArray();

        return [
            'found' => true,
            'item_id' => $item->id,
            'tag_id' => $item->tag_id,
            'material_name' => $item->material?->name ?? 'Material #'.$item->material_id,
            'material_code' => $item->material?->code,
            'batch' => $item->batch,
            'quantity' => (float) $item->quantity,
            'uom' => $item->uom,
            'state' => $item->state?->value,
            'state_label' => $item->state?->getLabel() ?? 'Tidak diketahui',
            'location' => $item->location ?? 'Gudang Utama',
            'client_name' => $item->client?->name ?? $item->order?->client?->name ?? '-',
            'client_code' => $item->client?->code ?? $item->order?->client?->code ?? '-',
            'order' => [
                'order_id' => $item->order_id,
                'order_number' => $item->order?->order_number ?? '-',
                'style_or_article' => $item->order?->article?->name ?? $item->order?->style_name ?? '-',
                'order_status' => $item->order?->status?->getLabel() ?? '-',
            ],
            'current_activity' => $activityDescription,
            'dispatch_info' => $latestDispatch ? [
                'dispatch_number' => $latestDispatch->dispatch_number,
                'status' => $latestDispatch->status?->value,
                'status_label' => $latestDispatch->status?->getLabel(),
                'cutting_started_at' => $latestDispatch->cutting_started_at?->format('d/m/Y H:i'),
                'received_at' => $latestDispatch->received_at?->format('d/m/Y H:i'),
                'dispatched_at' => $latestDispatch->dispatched_at?->format('d/m/Y H:i'),
            ] : null,
            'recent_movements' => $recentMovements,
        ];
    }

    /**
     * Get client billing info: how much client has to pay, unpaid invoices, overdue status.
     *
     * @param  string|int  $identifier  Client ID, code (e.g. CLI1), or name (e.g. Zara)
     * @return array<string, mixed>
     */
    public function getClientBilling(string|int $identifier): array
    {
        $cleanId = trim((string) $identifier);

        $client = null;

        // 1. Direct ID match
        if (is_numeric($cleanId)) {
            $client = Client::with('orders')->find((int) $cleanId);
        }

        // 2. Code match
        if (! $client) {
            $client = Client::with('orders')->where('code', 'ilike', $cleanId)->first();
        }

        // 3. Name fuzzy match
        if (! $client) {
            $client = Client::with('orders')->where('name', 'ilike', "%{$cleanId}%")->first();
        }

        if (! $client) {
            return [
                'found' => false,
                'identifier' => $cleanId,
                'message' => "Klien dengan identitas '{$cleanId}' tidak ditemukan di database.",
            ];
        }

        // Invoices for this client
        $invoices = Invoice::with(['order.article'])
            ->where('client_id', $client->id)
            ->orderBy('issued_at', 'desc')
            ->get();

        $issuedInvoices = $invoices->where('status', InvoiceStatus::ISSUED);
        $paidInvoices = $invoices->where('status', InvoiceStatus::PAID);
        $draftInvoices = $invoices->where('status', InvoiceStatus::DRAFT);

        $totalUnpaid = (float) $issuedInvoices->sum('amount');
        $totalPaid = (float) $paidInvoices->sum('amount');
        $totalDraft = (float) $draftInvoices->sum('amount');

        $now = Carbon::now()->startOfDay();

        $unpaidList = $issuedInvoices->map(function (Invoice $inv) use ($now): array {
            $dueDate = $inv->due_at ? Carbon::parse($inv->due_at)->startOfDay() : null;
            $isOverdue = $dueDate !== null && $dueDate->lt($now);
            $daysDiff = $dueDate !== null ? (int) $now->diffInDays($dueDate, false) : 0;

            return [
                'invoice_id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'order_number' => $inv->order?->order_number ?? '-',
                'style_name' => $inv->order?->article?->name ?? $inv->order?->style_name ?? '-',
                'amount' => (float) $inv->amount,
                'amount_formatted' => 'Rp '.number_format((float) $inv->amount, 0, ',', '.'),
                'sewing_amount' => (float) $inv->sewing_amount,
                'overhead_amount' => (float) $inv->overhead_amount,
                'tax_amount' => (float) $inv->tax_amount,
                'issued_at' => $inv->issued_at?->format('d/m/Y'),
                'due_at' => $inv->due_at?->format('d/m/Y'),
                'is_overdue' => $isOverdue,
                'overdue_status' => $isOverdue
                    ? "Lewat jatuh tempo {$daysDiff} hari"
                    : ($dueDate ? "Jatuh tempo dalam {$daysDiff} hari" : 'Tanpa tanggal jatuh tempo'),
            ];
        })->values()->toArray();

        $paidList = $paidInvoices->take(5)->map(fn (Invoice $inv) => [
            'invoice_id' => $inv->id,
            'invoice_number' => $inv->invoice_number,
            'order_number' => $inv->order?->order_number ?? '-',
            'amount' => (float) $inv->amount,
            'amount_formatted' => 'Rp '.number_format((float) $inv->amount, 0, ',', '.'),
            'paid_date' => $inv->updated_at?->format('d/m/Y'),
        ])->values()->toArray();

        $overdueCount = count(array_filter($unpaidList, fn ($inv) => $inv['is_overdue']));

        return [
            'found' => true,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_code' => $client->code,
            'contact_person' => $client->contact_person,
            'phone' => $client->phone,
            'email' => $client->email,
            'total_harus_dibayar' => $totalUnpaid,
            'total_harus_dibayar_formatted' => 'Rp '.number_format($totalUnpaid, 0, ',', '.'),
            'total_sudah_dibayar' => $totalPaid,
            'total_sudah_dibayar_formatted' => 'Rp '.number_format($totalPaid, 0, ',', '.'),
            'total_draft_tagihan' => $totalDraft,
            'total_draft_formatted' => 'Rp '.number_format($totalDraft, 0, ',', '.'),
            'jumlah_invoice_belum_lunas' => count($unpaidList),
            'jumlah_invoice_overdue' => $overdueCount,
            'unpaid_invoices' => $unpaidList,
            'recent_paid_invoices' => $paidList,
            'ringkasan_teks' => sprintf(
                'Klien %s (%s) memiliki total kewajiban bayar sebesar Rp %s dari %d invoice yang belum lunas (%d invoice sudah jatuh tempo).',
                $client->name,
                $client->code,
                number_format($totalUnpaid, 0, ',', '.'),
                count($unpaidList),
                $overdueCount,
            ),
        ];
    }

    /**
     * Get order status and production progress for owner.
     *
     * @param  string|int  $identifier  Order ID or Order Number
     * @return array<string, mixed>
     */
    public function getOrderStatus(string|int $identifier): array
    {
        $cleanId = trim((string) $identifier);

        $order = null;

        if (is_numeric($cleanId)) {
            $order = Order::with(['client', 'article', 'invoices', 'inventoryItems.material'])->find((int) $cleanId);
        }

        if (! $order) {
            $order = Order::with(['client', 'article', 'invoices', 'inventoryItems.material'])
                ->where('order_number', 'ilike', "%{$cleanId}%")
                ->first();
        }

        if (! $order) {
            return [
                'found' => false,
                'identifier' => $cleanId,
                'message' => "Pesanan / order dengan identitas '{$cleanId}' tidak ditemukan di database.",
            ];
        }

        $invoices = $order->invoices;
        $unpaidInvoices = $invoices->where('status', InvoiceStatus::ISSUED);
        $totalUnpaid = (float) $unpaidInvoices->sum('amount');

        return [
            'found' => true,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'client_name' => $order->client?->name ?? '-',
            'client_code' => $order->client?->code ?? '-',
            'article_name' => $order->article?->name ?? $order->style_name ?? '-',
            'status' => $order->status?->value,
            'status_label' => $order->status?->getLabel() ?? '-',
            'total_quantity' => (int) $order->total_quantity,
            'target_delivery_date' => $order->target_delivery_date?->format('d/m/Y'),
            'inventory_items_count' => $order->inventoryItems->count(),
            'invoices_count' => $invoices->count(),
            'total_unpaid_invoices' => $totalUnpaid,
            'total_unpaid_formatted' => 'Rp '.number_format($totalUnpaid, 0, ',', '.'),
        ];
    }

    /**
     * Get high-level executive dashboard summary for the Owner.
     *
     * @return array<string, mixed>
     */
    public function getOwnerExecutiveOverview(): array
    {
        // 1. Orders
        $activeOrders = Order::whereNotIn('status', [OrderStatus::COMPLETED, OrderStatus::CANCELLED])->count();
        $inProductionOrders = Order::where('status', OrderStatus::IN_PRODUCTION)->count();
        $inQcOrders = Order::where('status', OrderStatus::IN_QC)->count();
        $completedThisMonth = Order::where('status', OrderStatus::COMPLETED)
            ->where('updated_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        // 2. Financials (Receivables / Piutang)
        $unpaidInvoices = Invoice::with('client')->where('status', InvoiceStatus::ISSUED)->get();
        $totalPiutang = (float) $unpaidInvoices->sum('amount');

        $now = Carbon::now()->startOfDay();
        $overdueInvoices = $unpaidInvoices->filter(fn ($inv) => $inv->due_at && Carbon::parse($inv->due_at)->lt($now));
        $totalOverdue = (float) $overdueInvoices->sum('amount');

        // Top clients with outstanding balance
        $clientBalances = $unpaidInvoices->groupBy('client_id')->map(function (Collection $group): array {
            /** @var Invoice $first */
            $first = $group->first();

            return [
                'client_id' => $first->client_id,
                'client_name' => $first->client?->name ?? 'Klien #'.$first->client_id,
                'client_code' => $first->client?->code ?? '-',
                'total_hutang' => (float) $group->sum('amount'),
                'total_hutang_formatted' => 'Rp '.number_format((float) $group->sum('amount'), 0, ',', '.'),
                'invoice_count' => $group->count(),
            ];
        })->sortByDesc('total_hutang')->values()->take(5)->toArray();

        // 3. Inventory & Cutting activity
        $totalItems = InventoryItem::count();
        $rawCount = InventoryItem::where('state', InventoryState::RAW)->count();
        $wipCount = InventoryItem::where('state', InventoryState::WIP)->count();
        $finishedCount = InventoryItem::where('state', InventoryState::FINISHED)->count();

        $itemsInCutting = InventoryItem::whereHas('dispatches', function ($q) {
            $q->where('status', DispatchStatus::IN_CUTTING);
        })->count();

        return [
            'orders' => [
                'active_orders' => $activeOrders,
                'in_production' => $inProductionOrders,
                'in_qc' => $inQcOrders,
                'completed_this_month' => $completedThisMonth,
            ],
            'finance' => [
                'total_piutang' => $totalPiutang,
                'total_piutang_formatted' => 'Rp '.number_format($totalPiutang, 0, ',', '.'),
                'total_overdue' => $totalOverdue,
                'total_overdue_formatted' => 'Rp '.number_format($totalOverdue, 0, ',', '.'),
                'unpaid_invoice_count' => $unpaidInvoices->count(),
                'overdue_invoice_count' => $overdueInvoices->count(),
                'top_debtor_clients' => $clientBalances,
            ],
            'inventory' => [
                'total_items' => $totalItems,
                'raw_materials' => $rawCount,
                'wip_in_progress' => $wipCount,
                'finished_goods' => $finishedCount,
                'materials_currently_in_cutting' => $itemsInCutting,
            ],
        ];
    }

    /**
     * Resolve human explanation of what the item is currently doing.
     */
    private function resolveItemActivity(InventoryItem $item, ?MaterialDispatch $dispatch): string
    {
        if ($dispatch) {
            $dispatchLabel = $dispatch->status?->getLabel() ?? $dispatch->status?->value;

            return match ($dispatch->status) {
                DispatchStatus::IN_CUTTING => "Sedang dalam proses pemotongan (cutting) di meja potong sejak {$dispatch->cutting_started_at?->format('d/m/Y H:i')}. Nomor Pengantaran: {$dispatch->dispatch_number}.",
                DispatchStatus::IDLE => "Material telah berada di area produksi dan menunggu antrean meja potong (Status: {$dispatchLabel}).",
                DispatchStatus::DISPATCHED, DispatchStatus::PENDING_DELIVERY => "Material sedang dalam perjalanan diantar dari gudang ke area produksi (Status: {$dispatchLabel}).",
                DispatchStatus::RECEIVED => "Material telah diterima oleh tim produksi pada {$dispatch->received_at?->format('d/m/Y H:i')}, siap diproses.",
                DispatchStatus::COMPLETED => "Proses pemotongan (cutting) telah selesai pada {$dispatch->completed_at?->format('d/m/Y H:i')}. Komponen siap dialokasikan ke penjahit.",
                DispatchStatus::REQUESTED => "Ada permintaan pengeluaran material ke cutting (Nomor: {$dispatch->dispatch_number}), menunggu persetujuan.",
                DispatchStatus::APPROVED => 'Permintaan pengeluaran telah disetujui, sedang disiapkan oleh gudang untuk dikirim.',
                DispatchStatus::REJECTED => 'Pengeluaran material ditolak (Alasan: '.($dispatch->rejection_reason ?? '-').').',
                default => "Status pengantaran: {$dispatchLabel}.",
            };
        }

        // No dispatch recorded
        $stateLabel = $item->state?->getLabel() ?? $item->state?->value;
        $location = $item->location ?? 'Gudang Utama';

        return "Material tersimpan di gudang ({$location}) dalam kondisi {$stateLabel}. Belum ada pengajuan pengantaran ke lantai produksi.";
    }
}
