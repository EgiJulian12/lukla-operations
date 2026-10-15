<?php

namespace App\Http\Resources;

use App\Models\OutputEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Curated OutputEntry representation for Telegram bot / Bedrock context.
 *
 * @property-read OutputEntry $resource
 */
class OutputEntryResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     order_id: int,
     *     quantity: int,
     *     interval_start: string,
     *     interval_end: string,
     *     status: string,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'order_id' => $this->resource->order_id,
            'quantity' => $this->resource->quantity,
            'interval_start' => $this->resource->interval_start->toDateTimeString(),
            'interval_end' => $this->resource->interval_end->toDateTimeString(),
            'status' => $this->resource->status->getLabel(),
        ];
    }
}
