<?php

namespace Tests\Feature\Portal;

use App\Enums\UserRole;
use App\Filament\Portal\Resources\InventoryItems\PortalInventoryItemResource;
use App\Filament\Portal\Resources\Orders\Pages\ViewPortalOrder;
use App\Filament\Portal\Resources\Orders\PortalOrderResource;
use App\Filament\Portal\Resources\QcRecords\PortalQcRecordResource;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\QcRecord;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Portal klien maklon: satu klien hanya melihat datanya sendiri (FR-025,
 * NFR-004). Isolasi diuji lewat query resource dan lewat permintaan HTTP nyata
 * ke halaman rincian pesanan.
 */
class PortalClientViewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_portal_queries_are_scoped_to_the_logged_in_client(): void
    {
        $mine = Client::factory()->create();
        $other = Client::factory()->create();

        $order = Order::factory()->create(['client_id' => $mine->getKey()]);
        $otherOrder = Order::factory()->create(['client_id' => $other->getKey()]);

        QcRecord::factory()->create(['order_id' => $order->getKey(), 'client_id' => $mine->getKey()]);
        QcRecord::factory()->create(['order_id' => $otherOrder->getKey(), 'client_id' => $other->getKey()]);

        InventoryItem::factory()->create(['client_id' => $mine->getKey(), 'order_id' => $order->getKey()]);
        InventoryItem::factory()->create(['client_id' => $other->getKey(), 'order_id' => $otherOrder->getKey()]);

        $this->actingAs($this->clientUser($mine));

        $this->assertSame(1, PortalOrderResource::getEloquentQuery()->count());
        $this->assertSame(1, PortalQcRecordResource::getEloquentQuery()->count());
        $this->assertSame(1, PortalInventoryItemResource::getEloquentQuery()->count());
    }

    public function test_a_client_can_open_its_own_order_detail(): void
    {
        $mine = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $mine->getKey()]);

        $this->actingAs($this->clientUser($mine));

        $this->get(ViewPortalOrder::getUrl(['record' => $order], panel: 'portal', tenant: $mine))
            ->assertOk();
    }

    public function test_a_client_cannot_open_another_clients_order_detail(): void
    {
        $mine = Client::factory()->create();
        $other = Client::factory()->create();

        $otherOrder = Order::factory()->create(['client_id' => $other->getKey()]);

        $this->actingAs($this->clientUser($mine));

        $this->get(ViewPortalOrder::getUrl(['record' => $otherOrder], panel: 'portal', tenant: $mine))
            ->assertNotFound();
    }

    public function test_a_client_can_open_the_portal_qc_list(): void
    {
        $mine = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $mine->getKey()]);

        QcRecord::factory()->create(['order_id' => $order->getKey(), 'client_id' => $mine->getKey()]);

        $this->actingAs($this->clientUser($mine));

        $this->get(PortalQcRecordResource::getUrl(panel: 'portal', tenant: $mine))
            ->assertOk();
    }

    public function test_portal_sidebar_hides_qc_and_inventory_menus(): void
    {
        $this->assertFalse(PortalQcRecordResource::shouldRegisterNavigation());
        $this->assertFalse(PortalInventoryItemResource::shouldRegisterNavigation());
        $this->assertTrue(PortalOrderResource::shouldRegisterNavigation());
    }

    private function clientUser(Client $client): User
    {
        $user = User::factory()->create(['client_id' => $client->getKey()]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        return $user;
    }
}
