<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Curated Order representation for Telegram bot / Bedrock context.
 *
 * Exposes only business-relevant fields — no audit, no internal IDs,
 * no timestamps beyond what a client legitimately needs to know.
 *
 * @property-read Order $resource
 */
class OrderResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     order_number: string,
     *     status: string,
     *     work_scheme: string,
     *     quantity: int,
     *     due_date: string|null,
     *     description: string|null,
     *     warehouse_material_status: string,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'order_number' => $this->resource->order_number,
            'status' => $this->resource->status->getLabel(),
            'work_scheme' => $this->resource->work_scheme->getLabel(),
            'quantity' => $this->resource->quantity,
            'due_date' => $this->resource->due_date?->toDateString(),
            'description' => $this->resource->description,
            'warehouse_material_status' => $this->resource->warehouse_material_status->getLabel(),
        ];
    }
}
