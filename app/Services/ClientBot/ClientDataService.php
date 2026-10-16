<?php

namespace App\Services\ClientBot;

use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OutputEntry;
use App\Models\RateCard;
use App\Services\Ai\RagService;

/**
 * Operasional data klien untuk agent AI client bot.
 *
 * Semua query berjalan tanpa session auth (konteks webhook), jadi scoping
 * client_id selalu eksplisit: tanpa global scope, hanya where client_id
 * di sini yang melindungi data antar klien.
 */
class ClientDataService
{
    public function __construct(
        private readonly RagService $ragService,
    ) {}

    /**
     * Dokumen SOP / SOP / aturan bisnis.
     *
     * @return array<string, mixed>
     */
    public function searchKnowledgeBase(string $query): array
    {
        $cleanQuery = trim($query);

        if ($cleanQuery === '') {
            return ['found' => false, 'message' => 'Query pencarian kosong.', 'documents' => []];
        }

        try {
            $chunks = $this->ragService->searchDocuments($cleanQuery, 4);
        } catch (\Throwable $e) {
            return [
                'found' => false,
                'message' => 'Gagal membaca dokumen: '.$e->getMessage(),
                'documents' => [],
            ];
        }

        if ($chunks === []) {
            return [
                'found' => false,
                'message' => "Tidak ada dokumen yang cocok dengan kata kunci '{$cleanQuery}'.",
                'documents' => [],
            ];
        }

        return [
            'found' => true,
            'documents' => array_map(static fn (array $chunk): array => [
                'source' => $chunk['source'],
                'excerpt' => str($chunk['content'])->limit(300),
            ], $chunks),
        ];
    }

    /**
     * Order milik klien, terbaru dulu.
     *
     * @param  array{status?: string}  $filters
     * @return array<string, mixed>
     */
    public function getOrders(int $clientId, array $filters = []): array
    {
        $query = Order::query()
            ->withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->latest();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $orders = $query->limit(10)->get();

        if ($orders->isEmpty()) {
            return ['found' => false, 'message' => 'Belum ada order untuk klien ini.', 'orders' => []];
        }

        return [
            'found' => true,
            'count' => $orders->count(),
            'orders' => $orders->map(fn (Order $order): array => [
                'order_number' => $order->order_number,
                'article_name' => $order->article?->name,
                'status' => $order->status->getLabel(),
                'work_scheme' => $order->work_scheme->getLabel(),
                'quantity' => $order->quantity,
                'due_date' => $order->due_date?->toDateString(),
            ])->toArray(),
        ];
    }

    /**
     * Status satu order, dicari lewat nomor order atau ID.
     *
     * @return array<string, mixed>
     */
    public function getOrderStatus(int $clientId, string|int $identifier): array
    {
        $identifier = trim((string) $identifier);

        $order = Order::query()
            ->withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->where(function ($query) use ($identifier): void {
                if (is_numeric($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
                $query->orWhere('order_number', 'ilike', "%{$identifier}%");
            })
            ->with(['article', 'inventoryItems', 'outputEntries'])
            ->first();

        if ($order === null) {
            return ['found' => false, 'message' => "Order '{$identifier}' tidak ditemukan di data klien Anda."];
        }

        return [
            'found' => true,
            'order_number' => $order->order_number,
            'article_name' => $order->article?->name,
            'status' => $order->status->getLabel(),
            'awaiting_client_approval' => $order->status->awaitingClientApproval(),
            'work_scheme' => $order->work_scheme->getLabel(),
            'quantity' => $order->quantity,
            'due_date' => $order->due_date?->toDateString(),
            'output_summary' => $order->outputEntries->sum('quantity'),
            'inventory_items_count' => $order->inventoryItems->count(),
        ];
    }

    /**
     * Stok/bahan klien beserta kondisi dan lokasinya.
     *
     * @param  array{state?: string}  $filters
     * @return array<string, mixed>
     */
    public function getInventoryItems(int $clientId, array $filters = []): array
    {
        $query = InventoryItem::query()
            ->withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->with('material')
            ->latest();

        if (isset($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        $items = $query->limit(15)->get();

        if ($items->isEmpty()) {
            return ['found' => false, 'message' => 'Belum ada data stok untuk klien ini.', 'items' => []];
        }

        return [
            'found' => true,
            'count' => $items->count(),
            'items' => $items->map(fn (InventoryItem $item): array => [
                'material_name' => $item->material?->name ?? 'Material #'.$item->material_id,
                'batch' => $item->batch,
                'state' => $item->state->getLabel(),
                'quantity' => (float) $item->quantity,
                'uom' => $item->uom,
                'location' => $item->location,
            ])->toArray(),
        ];
    }

    /**
     * Produksi tercatat (hasil jahit per interval) milik klien.
     *
     * @param  array{order_number?: string}  $filters
     * @return array<string, mixed>
     */
    public function getOutputEntries(int $clientId, array $filters = []): array
    {
        $query = OutputEntry::query()
            ->withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->with(['order', 'component', 'productionLine'])
            ->latest('interval_start');

        if (isset($filters['order_number'])) {
            $query->whereHas('order', fn ($q) => $q->where('order_number', 'ilike', "%{$filters['order_number']}%"));
        }

        $entries = $query->limit(15)->get();

        if ($entries->isEmpty()) {
            return ['found' => false, 'message' => 'Belum ada catatan produksi untuk klien ini.', 'entries' => []];
        }

        return [
            'found' => true,
            'count' => $entries->count(),
            'entries' => $entries->map(fn (OutputEntry $entry): array => [
                'order_number' => $entry->order?->order_number,
                'component_name' => $entry->component?->name,
                'line_name' => $entry->productionLine?->name,
                'quantity' => $entry->quantity,
                'status' => $entry->status->getLabel(),
                'interval_start' => $entry->interval_start?->toDateTimeString(),
            ])->toArray(),
        ];
    }

    /**
     * Rate card yang sudah disetujui, harga per unit jasa.
     *
     * @param  array{rate_type?: string}  $filters
     * @return array<string, mixed>
     */
    public function getRateCards(int $clientId, array $filters = []): array
    {
        $query = RateCard::query()
            ->withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->approved()
            ->with(['article', 'component'])
            ->orderByDesc('effective_from');

        if (isset($filters['rate_type'])) {
            $query->where('rate_type', $filters['rate_type']);
        }

        $cards = $query->limit(15)->get();

        if ($cards->isEmpty()) {
            return ['found' => false, 'message' => 'Belum ada rate card yang disetujui untuk klien ini.', 'rate_cards' => []];
        }

        return [
            'found' => true,
            'count' => $cards->count(),
            'rate_cards' => $cards->map(fn (RateCard $card): array => [
                'article_name' => $card->article?->name,
                'component_name' => $card->component?->name,
                'rate_type' => $card->rate_type->value,
                'rate' => (float) $card->rate,
                'effective_from' => $card->effective_from->toDateString(),
            ])->toArray(),
        ];
    }
}
