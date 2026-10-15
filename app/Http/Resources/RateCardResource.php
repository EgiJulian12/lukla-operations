<?php

namespace App\Http\Resources;

use App\Models\RateCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Curated RateCard representation for Telegram bot / Bedrock context.
 *
 * @property-read RateCard $resource
 */
class RateCardResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     rate_type: string,
     *     rate: string,
     *     status: string,
     *     effective_from: string,
     *     effective_to: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'rate_type' => $this->resource->rate_type->getLabel(),
            'rate' => $this->resource->rate,
            'status' => $this->resource->status->getLabel(),
            'effective_from' => $this->resource->effective_from->toDateString(),
            'effective_to' => $this->resource->effective_to?->toDateString(),
        ];
    }
}
