<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 *
 * client_id dan order_id diturunkan dari item stok, bukan dibangkitkan
 * sendiri. Kalau keduanya dibuat acak, FK komposit
 * (inventory_item_id, client_id, order_id) akan menolak barisnya — dan
 * memang itulah yang diinginkan sistem ini.
 */
class InventoryMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'client_id' => fn (array $attributes): ?int => InventoryItem::find($attributes['inventory_item_id'])?->client_id,
            'order_id' => fn (array $attributes): ?int => InventoryItem::find($attributes['inventory_item_id'])?->order_id,
            'movement_type' => MovementType::RECEIPT,
            'quantity' => 10,
            'reference' => null,
            'notes' => null,
            'created_by' => null,
            'recorded_at' => now(),
        ];
    }
}
