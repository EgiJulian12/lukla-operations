<?php

namespace App\Http\Resources;

use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Curated InventoryItem representation for Telegram bot / Bedrock context.
 *
 * @property-read InventoryItem $resource
 */
class InventoryItemResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     batch: string|null,
     *     state: string,
     *     quantity: string,
     *     uom: string,
     *     location: string|null,
     *     material: array<string, mixed>|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'batch' => $this->resource->batch,
            'state' => $this->resource->state->getLabel(),
            'quantity' => $this->resource->quantity,
            'uom' => $this->resource->uom,
            'location' => $this->resource->location,
            'material' => $this->resource->relationLoaded('material') && $this->resource->material !== null
                ? (new MaterialResource($this->resource->material))->toArray($request)
                : null,
        ];
    }
}
