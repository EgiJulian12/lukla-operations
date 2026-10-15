<?php

namespace Database\Factories;

use App\Models\Component;
use App\Models\Order;
use App\Models\OrderComponentAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderComponentAssignment>
 */
class OrderComponentAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'component_id' => Component::factory(),
            'user_id' => User::factory(),
            'assigned_at' => now(),
        ];
    }
}
