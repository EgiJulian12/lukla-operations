<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Material;
use App\Models\Order;
use App\Models\WasteRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WasteRecord>
 *
 * Seperti InventoryItemFactory, pesanan diturunkan dari klien yang sama agar
 * catatan sisa potongan selalu teratribusi ke pemilik yang benar (FR-008).
 */
class WasteRecordFactory extends Factory
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
            'quantity' => fake()->randomFloat(2, 0.5, 10),
            'uom' => 'meter',
            'category' => fake()->randomElement(['Sisa potong', 'Cacat bahan', 'Uji coba']),
            'notes' => null,
            'recorded_by' => null,
        ];
    }
}
