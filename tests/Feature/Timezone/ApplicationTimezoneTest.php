<?php

namespace Tests\Feature\Timezone;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Portal\Resources\Orders\Pages\ViewPortalOrder;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_application_timezone_is_configured_to_asia_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
        $this->assertSame('Asia/Jakarta', now()->getTimezone()->getName());
    }

    public function test_infolist_displays_datetime_in_local_wib_timezone(): void
    {
        $client = Client::factory()->create();
        $clientUser = User::factory()->create([
            'client_id' => $client->id,
        ]);
        $clientUser->assignRole(UserRole::CLIENT_MAKLON->value);

        // Set waktu spesifik di WIB: 2026-10-15 20:20:17
        $testTime = Carbon::create(2026, 10, 15, 20, 20, 17, 'Asia/Jakarta');

        $order = Order::factory()->create([
            'client_id' => $client->id,
            'status' => OrderStatus::QUOTED,
            'estimated_cost' => 9000000,
            'quoted_at' => $testTime,
            'due_date' => now()->addDays(14)->toDateString(),
        ]);

        $this->actingAs($clientUser);

        $response = $this->get(ViewPortalOrder::getUrl(['record' => $order], panel: 'portal', tenant: $client));
        $response->assertOk();

        // Di infolist DateTimeEntry, waktu harus tampil jam 20:20:17 (bukan 13:20:17)
        $response->assertSee('20:20:17');
        $response->assertDontSee('13:20:17');
    }
}
