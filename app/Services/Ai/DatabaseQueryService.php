<?php

namespace App\Services\Ai;

use App\Http\Resources\InventoryItemResource;
use App\Http\Resources\MaterialResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OutputEntryResource;
use App\Http\Resources\RateCardResource;
use App\Models\InventoryItem;
use App\Models\Material;
use App\Models\Order;
use App\Models\OutputEntry;
use App\Models\RateCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Executes curated, client-scoped Eloquent queries for the Telegram bot.
 *
 * Every query is explicitly scoped to the authenticated client's client_id —
 * either via the BelongsToClient::scopeForClient() escape hatch (for models that
 * carry the trait) or via a manual where() clause. This ensures that even though
 * the Telegram endpoint is unauthenticated at the HTTP layer, each data fetch is
 * bounded to the requesting client's data only.
 *
 * The results are serialised through the corresponding Eloquent API Resource
 * so no audit fields, internal IDs, or sensitive columns are ever returned.
 */
class DatabaseQueryService
{
    private const DEFAULT_LIMIT = 20;

    /**
     * Dispatch a query for the given model key, scoped to the client.
     *
     * @param  array<string, mixed>  $filters
     * @return array{model: string, count: int, data: list<array<string, mixed>>}
     */
    public function query(string $model, int $clientId, array $filters = [], int $limit = self::DEFAULT_LIMIT): array
    {
        $result = match ($model) {
            'orders' => $this->queryOrders($clientId, $filters, $limit),
            'materials' => $this->queryMaterials($clientId, $filters, $limit),
            'inventory_items' => $this->queryInventoryItems($clientId, $filters, $limit),
            'output_entries' => $this->queryOutputEntries($clientId, $filters, $limit),
            'rate_cards' => $this->queryRateCards($clientId, $filters, $limit),
            default => ['model' => $model, 'count' => 0, 'data' => []],
        };

        Log::debug('DatabaseQueryService: executed query.', [
            'model' => $model,
            'client_id' => $clientId,
            'filters' => $filters,
            'count' => $result['count'],
        ]);

        return $result;
    }

    /**
     * Query orders for the given client.
     *
     * Supported filters: status (string), order_number (string).
     *
     * @param  array<string, mixed>  $filters
     * @return array{model: string, count: int, data: list<array<string, mixed>>}
     */
    private function queryOrders(int $clientId, array $filters, int $limit): array
    {
        $query = Order::query()
            ->withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->latest();

        if (isset($filters['order_number'])) {
            $query->where('order_number', $filters['order_number']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $records = $query->limit($limit)->get();

        $data = OrderResource::collection($records)
            ->toArray(new Request);

        return ['model' => 'orders', 'count' => $records->count(), 'data' => $data];
    }

    /**
     * Query materials for the given client.
     *
     * Returns both client-specific materials and generic (cross-client) materials.
     * Material does not use BelongsToClient, so we apply the filter manually.
     *
     * @param  array<string, mixed>  $filters
     * @return array{model: string, count: int, data: list<array<string, mixed>>}
     */
    private function queryMaterials(int $clientId, array $filters, int $limit): array
    {
        $query = Material::query()
            ->where(function ($q) use ($clientId): void {
                $q->where('client_id', $clientId)
                    ->orWhereNull('client_id');
            });

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        $records = $query->limit($limit)->get();

        $data = MaterialResource::collection($records)
            ->toArray(new Request);

        return ['model' => 'materials', 'count' => $records->count(), 'data' => $data];
    }

    /**
     * Query inventory items for the given client.
     *
     * Supported filters: state (string), order_id (int).
     *
     * @param  array<string, mixed>  $filters
     * @return array{model: string, count: int, data: list<array<string, mixed>>}
     */
    private function queryInventoryItems(int $clientId, array $filters, int $limit): array
    {
        $query = InventoryItem::query()
            ->withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->with('material')
            ->latest();

        if (isset($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        if (isset($filters['order_id'])) {
            $query->where('order_id', (int) $filters['order_id']);
        }

        $records = $query->limit($limit)->get();

        $data = InventoryItemResource::collection($records)
            ->toArray(new Request);

        return ['model' => 'inventory_items', 'count' => $records->count(), 'data' => $data];
    }

    /**
     * Query output entries for the given client.
     *
     * Supported filters: status (string), order_id (int).
     *
     * @param  array<string, mixed>  $filters
     * @return array{model: string, count: int, data: list<array<string, mixed>>}
     */
    private function queryOutputEntries(int $clientId, array $filters, int $limit): array
    {
        $query = OutputEntry::query()
            ->withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->latest();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['order_id'])) {
            $query->where('order_id', (int) $filters['order_id']);
        }

        $records = $query->limit($limit)->get();

        $data = OutputEntryResource::collection($records)
            ->toArray(new Request);

        return ['model' => 'output_entries', 'count' => $records->count(), 'data' => $data];
    }

    /**
     * Query approved rate cards for the given client.
     *
     * Supported filters: rate_type (string).
     *
     * @param  array<string, mixed>  $filters
     * @return array{model: string, count: int, data: list<array<string, mixed>>}
     */
    private function queryRateCards(int $clientId, array $filters, int $limit): array
    {
        $query = RateCard::query()
            ->withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->approved();

        if (isset($filters['rate_type'])) {
            $query->where('rate_type', $filters['rate_type']);
        }

        $records = $query->limit($limit)->get();

        $data = RateCardResource::collection($records)
            ->toArray(new Request);

        return ['model' => 'rate_cards', 'count' => $records->count(), 'data' => $data];
    }
}
