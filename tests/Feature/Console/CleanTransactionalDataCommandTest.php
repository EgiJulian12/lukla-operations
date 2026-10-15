<?php

namespace Tests\Feature\Console;

use App\Models\Client;
use App\Models\Order;
use App\Models\Sample;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanTransactionalDataCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_it_cleans_transactional_data_while_preserving_users_and_clients(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();

        Order::factory()->create(['client_id' => $client->id]);
        Sample::factory()->create(['client_id' => $client->id]);

        $this->assertGreaterThan(0, Order::count());
        $this->assertGreaterThan(0, Sample::count());

        $this->artisan('app:clean-transactions', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Data transaksional berhasil dibersihkan.');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Sample::count());
        $this->assertSame(0, DB::table('order_component_assignments')->count());
        $this->assertSame(0, DB::table('sample_revisions')->count());

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_it_can_be_cancelled_when_not_confirmed(): void
    {
        $this->artisan('app:clean-transactions')
            ->expectsConfirmation('PERINGATAN: Perintah ini akan menghapus SEMUA data pesanan, sampel, transaksi, dan inventaris. Lanjutkan?', 'no')
            ->expectsOutput('Operasi dibatalkan.')
            ->assertSuccessful();
    }
}
