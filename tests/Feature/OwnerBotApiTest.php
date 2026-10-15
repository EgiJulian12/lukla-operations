<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use App\Enums\InventoryState;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\MaterialDispatch;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerBotApiTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = 'test-owner-secret-key-123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        config([
            'services.owner_bot.api_key' => $this->apiKey,
            'services.owner_bot.allowed_telegram_user_ids' => ['987654321'],
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/api/owner/chat', [
            'message' => 'Halo bot',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_authenticated_request_with_bot_token_header_is_accepted(): void
    {
        $response = $this->withHeader('X-Bot-Token', $this->apiKey)
            ->postJson('/api/owner/chat', [
                'message' => 'Ringkasan pabrik hari ini',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_authenticated_request_with_bearer_token_is_accepted(): void
    {
        $response = $this->withToken($this->apiKey)
            ->postJson('/api/owner/chat', [
                'message' => 'Ringkasan keuangan',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_authenticated_user_with_owner_role_can_access(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $response = $this->actingAs($owner)
            ->postJson('/api/owner/chat', [
                'message' => 'Status overview',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_authenticated_user_without_owner_role_is_denied(): void
    {
        $clientUser = User::factory()->create();
        $clientUser->assignRole(UserRole::CLIENT_MAKLON->value);

        $response = $this->actingAs($clientUser)
            ->postJson('/api/owner/chat', [
                'message' => 'Status overview',
            ]);

        $response->assertStatus(401);
    }

    public function test_track_inventory_item_direct_api(): void
    {
        $client = Client::factory()->create(['name' => 'PT Megah Tekstil', 'code' => 'MEG']);
        $order = Order::factory()->create(['client_id' => $client->id, 'order_number' => 'ORD-TEST-001']);
        $material = Material::factory()->create(['name' => 'Kain Katun Combed 30s', 'code' => 'KAT-30S']);

        $item = InventoryItem::factory()->create([
            'client_id' => $client->id,
            'order_id' => $order->id,
            'material_id' => $material->id,
            'state' => InventoryState::RAW,
            'quantity' => 150.5,
            'uom' => 'meter',
            'location' => 'Rak A-10',
        ]);

        // Add a dispatch indicating it is in cutting
        MaterialDispatch::create([
            'dispatch_number' => 'DSP-2026-0001',
            'client_id' => $client->id,
            'order_id' => $order->id,
            'inventory_item_id' => $item->id,
            'material_id' => $material->id,
            'quantity' => 50,
            'uom' => 'meter',
            'status' => DispatchStatus::IN_CUTTING,
            'cutting_started_at' => now()->subHours(2),
        ]);

        $response = $this->withHeader('X-Bot-Token', $this->apiKey)
            ->getJson("/api/owner/inventory/track/{$item->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'item_id' => $item->id,
                    'material_name' => 'Kain Katun Combed 30s',
                    'material_code' => 'KAT-30S',
                    'quantity' => 150.5,
                    'location' => 'Rak A-10',
                    'client_name' => 'PT Megah Tekstil',
                    'order' => [
                        'order_number' => 'ORD-TEST-001',
                    ],
                ],
            ]);

        $this->assertStringContainsString('cutting', strtolower($response->json('data.current_activity')));
    }

    public function test_client_billing_direct_api(): void
    {
        $client = Client::factory()->create(['name' => 'Zara Indonesia', 'code' => 'ZARA']);
        $order = Order::factory()->create(['client_id' => $client->id, 'order_number' => 'ORD-ZARA-01']);

        // Unpaid issued invoice
        Invoice::factory()->create([
            'client_id' => $client->id,
            'order_id' => $order->id,
            'invoice_number' => 'INV-ZARA-001',
            'status' => InvoiceStatus::ISSUED,
            'amount' => 25000000,
            'issued_at' => now()->subDays(5),
            'due_at' => now()->addDays(10),
        ]);

        // Paid invoice
        Invoice::factory()->create([
            'client_id' => $client->id,
            'order_id' => $order->id,
            'invoice_number' => 'INV-ZARA-PAID',
            'status' => InvoiceStatus::PAID,
            'amount' => 10000000,
            'issued_at' => now()->subDays(30),
            'due_at' => now()->subDays(15),
        ]);

        $response = $this->withHeader('X-Bot-Token', $this->apiKey)
            ->getJson("/api/owner/clients/{$client->id}/billing");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'client_id' => $client->id,
                    'client_name' => 'Zara Indonesia',
                    'total_harus_dibayar' => 25000000,
                    'total_sudah_dibayar' => 10000000,
                    'jumlah_invoice_belum_lunas' => 1,
                ],
            ]);
    }

    public function test_chat_endpoint_tracks_item_by_natural_language(): void
    {
        $client = Client::factory()->create(['name' => 'H&M Apparel']);
        $order = Order::factory()->create(['client_id' => $client->id, 'order_number' => 'ORD-HM-001']);
        $material = Material::factory()->create(['name' => 'Kain Denim 14oz']);

        $item = InventoryItem::factory()->create([
            'client_id' => $client->id,
            'order_id' => $order->id,
            'material_id' => $material->id,
            'quantity' => 200,
            'uom' => 'yard',
            'location' => 'Gudang Bahan Mentah Blok C',
        ]);

        $response = $this->withHeader('X-Bot-Token', $this->apiKey)
            ->postJson('/api/owner/chat', [
                'message' => "ID barang {$item->id} sedang ngapain?",
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'track_inventory_item',
            ]);

        $this->assertStringContainsString((string) $item->id, $response->json('reply'));
        $this->assertStringContainsString('Kain Denim 14oz', $response->json('reply'));
    }

    public function test_chat_endpoint_checks_client_billing_by_natural_language(): void
    {
        $client = Client::factory()->create(['name' => 'Uniqlo Fast Retailing', 'code' => 'UNIQLO']);

        Invoice::factory()->create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-UQ-001',
            'status' => InvoiceStatus::ISSUED,
            'amount' => 45000000,
            'due_at' => now()->addDays(5),
        ]);

        $response = $this->withHeader('X-Bot-Token', $this->apiKey)
            ->postJson('/api/owner/chat', [
                'message' => "ID klien {$client->id} harus bayar berapa?",
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'get_client_billing',
            ]);

        $this->assertStringContainsString('45.000.000', $response->json('reply'));
        $this->assertStringContainsString('Uniqlo Fast Retailing', $response->json('reply'));
    }

    public function test_overview_endpoint_returns_kpis(): void
    {
        $response = $this->withHeader('X-Bot-Token', $this->apiKey)
            ->getJson('/api/owner/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'orders' => ['active_orders', 'in_production', 'in_qc'],
                    'finance' => ['total_piutang', 'total_piutang_formatted'],
                    'inventory' => ['total_items'],
                ],
            ]);
    }

    public function test_telegram_webhook_start_command(): void
    {
        $response = $this->withHeader('X-Bot-Token', $this->apiKey)
            ->postJson('/api/owner/telegram/webhook', [
                'update_id' => 10001,
                'message' => [
                    'message_id' => 1,
                    'chat' => [
                        'id' => 987654321,
                        'type' => 'private',
                    ],
                    'from' => [
                        'id' => 987654321,
                        'first_name' => 'Owner',
                    ],
                    'text' => '/start',
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
            ]);

        $this->assertStringContainsString('Lukla Owner Bot', $response->json('reply'));
    }
}
