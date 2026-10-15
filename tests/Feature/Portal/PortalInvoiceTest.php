<?php

namespace Tests\Feature\Portal;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Filament\Portal\Resources\Invoices\PortalInvoiceResource;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_client_only_sees_issued_invoices_of_their_own_company(): void
    {
        $myClient = Client::factory()->create();
        $otherClient = Client::factory()->create();

        $myOrder = Order::factory()->create(['client_id' => $myClient->id]);
        $otherOrder = Order::factory()->create(['client_id' => $otherClient->id]);

        // Invoices for my client
        $issuedMine = Invoice::factory()->issued()->create([
            'client_id' => $myClient->id,
            'order_id' => $myOrder->id,
        ]);
        $draftMine = Invoice::factory()->create([
            'client_id' => $myClient->id,
            'order_id' => $myOrder->id,
            'status' => InvoiceStatus::DRAFT,
        ]);

        // Invoice for other client
        $issuedOther = Invoice::factory()->issued()->create([
            'client_id' => $otherClient->id,
            'order_id' => $otherOrder->id,
        ]);

        $user = User::factory()->create(['client_id' => $myClient->id]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        $this->actingAs($user);

        $query = PortalInvoiceResource::getEloquentQuery();

        $this->assertSame(1, $query->count());
        $this->assertSame($issuedMine->id, $query->first()->id);
    }

    public function test_client_can_access_portal_invoice_list_page(): void
    {
        $myClient = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $myClient->id]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        $this->actingAs($user);

        $this->get(PortalInvoiceResource::getUrl(panel: 'portal', tenant: $myClient))
            ->assertOk();
    }
}
