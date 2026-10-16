<?php

namespace App\Http\Resources;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Curated Material representation for Telegram bot / Bedrock context.
 *
 * @property-read Material $resource
 */
class MaterialResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     uom: string,
     *     category: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'uom' => $this->resource->uom,
            'category' => $this->resource->category,
        ];
    }
}
