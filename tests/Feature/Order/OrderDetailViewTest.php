<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Filament\Portal\Resources\Orders\Pages\ViewPortalOrder;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderDetailViewTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private User $owner;

    private User $timSample;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->client = Client::factory()->create([
            'name' => 'PT Maklon Sukses',
        ]);

        $this->owner = User::factory()->create();
        $this->owner->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $this->timSample = User::factory()->create();
        $this->timSample->assignRole(UserRole::SAMPLE_TEAM->value);
    }

    public function test_view_action_is_visible_on_orders_table(): void
    {
        $order = Order::factory()->create([
            'client_id' => $this->client->id,
            'status' => OrderStatus::REQUESTED,
            'order_number' => 'ORD-TEST-001',
        ]);

        $this->actingAs($this->owner);

        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('view', $order);
    }

    public function test_owner_can_access_view_order_page_and_see_details(): void
    {
        $order = Order::factory()->create([
            'client_id' => $this->client->id,
            'status' => OrderStatus::QUOTED,
            'work_scheme' => WorkScheme::CMT,
            'order_number' => 'ORD-TEST-002',
            'quantity' => 500,
            'estimated_cost' => 15000000,
            'quote_notes' => 'Termasuk finishing dan packaging.',
            'description' => 'Jaket bomber parasut warna navy.',
            'due_date' => now()->addDays(20),
        ]);

        $this->actingAs($this->owner);

        $response = $this->get(OrderResource::getUrl('view', ['record' => $order]));
        $response->assertOk();

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertSuccessful()
            ->assertSee('ORD-TEST-002')
            ->assertSee('PT Maklon Sukses')
            ->assertSee('Jaket bomber parasut warna navy.')
            ->assertSee('Termasuk finishing dan packaging.')
            ->assertActionVisible('back')
            ->assertActionVisible('edit');
    }

    public function test_tim_sample_can_access_view_order_page(): void
    {
        $order = Order::factory()->create([
            'client_id' => $this->client->id,
            'status' => OrderStatus::QUOTE_APPROVED,
            'order_number' => 'ORD-TEST-003',
        ]);

        $this->actingAs($this->timSample);

        $response = $this->get(OrderResource::getUrl('view', ['record' => $order]));
        $response->assertOk();

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertSuccessful()
            ->assertSee('ORD-TEST-003')
            ->assertSee('PT Maklon Sukses')
            ->assertActionVisible('back');
    }

    public function test_portal_order_view_page_remains_functional_for_client(): void
    {
        $clientUser = User::factory()->create([
            'client_id' => $this->client->id,
        ]);
        $clientUser->assignRole(UserRole::CLIENT_MAKLON->value);

        $order = Order::factory()->create([
            'client_id' => $this->client->id,
            'status' => OrderStatus::QUOTED,
            'order_number' => 'ORD-TEST-004',
            'estimated_cost' => 10000000,
        ]);

        $this->actingAs($clientUser);

        $response = $this->get(ViewPortalOrder::getUrl(['record' => $order], panel: 'portal', tenant: $this->client));
        $response->assertOk()
            ->assertSee('ORD-TEST-004');
    }
}
