<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'material_id' => Material::factory(),
            'quantity' => fake()->numberBetween(10, 500),
            'unit_price' => fake()->randomFloat(2, 5000, 90000),
        ];
    }
}
