<?php

namespace Database\Factories;

use App\Enums\InventoryState;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Material;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 *
 * Pesanan diturunkan dari klien yang sama, supaya factory tidak pernah
 * menghasilkan item "milik klien A untuk pesanan klien B" — keadaan yang
 * justru dilarang sistem (FR-005..007).
 */
class InventoryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'order_id' => fn (array $attributes): int => Order::factory()
                ->create(['client_id' => $attributes['client_id']])
                ->getKey(),
            'material_id' => Material::factory(),
            'batch' => fake()->bothify('BAT-##??'),
            'state' => InventoryState::RAW,
            'quantity' => 0,
            'uom' => 'meter',
            'location' => 'Rak '.fake()->bothify('?#'),
        ];
    }

    /**
     * Item dengan jumlah awal, untuk pengujian pergerakan keluar.
     */
    public function withStock(float $quantity): static
    {
        return $this->state(fn (): array => ['quantity' => $quantity]);
    }
}
