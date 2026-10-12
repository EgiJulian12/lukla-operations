<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Order;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Jejak audit (FR-022, NFR-005, NFR-006).
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_creating_a_stock_item_writes_an_audit_row(): void
    {
        $item = $this->itemFor(Client::factory()->create());

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $item->getMorphClass(),
            'auditable_id' => $item->getKey(),
            'event' => 'created',
        ]);
    }

    public function test_changing_stock_quantity_is_audited_with_before_and_after(): void
    {
        $item = $this->itemFor(Client::factory()->create());

        $item->update(['quantity' => 75]);

        $log = AuditLog::query()
            ->where('auditable_type', $item->getMorphClass())
            ->where('auditable_id', $item->getKey())
            ->where('event', 'updated')
            ->sole();

        $this->assertSame('0.00', (string) $log->before['quantity']);
        $this->assertEquals(75, (float) $log->after['quantity']);
    }

    public function test_the_audit_row_records_the_client_that_owns_the_record(): void
    {
        $client = Client::factory()->create();

        $item = $this->itemFor($client);

        $log = AuditLog::query()->where('event', 'created')->sole();

        $this->assertSame($client->getKey(), $log->client_id);
        $this->assertSame($item->order_id, $log->order_id);
    }

    public function test_audit_rows_cannot_be_updated(): void
    {
        $this->itemFor(Client::factory()->create());
        $log = AuditLog::query()->sole();

        $this->expectException(RuntimeException::class);

        $log->update(['event' => 'tampered']);
    }

    public function test_audit_rows_cannot_be_deleted(): void
    {
        $this->itemFor(Client::factory()->create());
        $log = AuditLog::query()->sole();

        $this->expectException(RuntimeException::class);

        $log->delete();
    }

    /**
     * Rantai hash: setiap baris mengikat baris sebelumnya, sehingga
     * penyuntingan di tengah rantai membuat hash tidak lagi cocok (NFR-005).
     */
    public function test_rows_form_a_verifiable_hash_chain(): void
    {
        $this->itemFor(Client::factory()->create());
        $this->itemFor(Client::factory()->create());

        $logs = AuditLog::query()->orderBy('id')->get();

        $this->assertCount(2, $logs);
        $this->assertNull($logs[0]->previous_hash);
        $this->assertSame($logs[0]->payload_hash, $logs[1]->previous_hash);

        foreach ($logs as $log) {
            $this->assertTrue($log->verifyChain(), "Baris audit #{$log->getKey()} tidak lolos verifikasi rantai.");
        }
    }

    /**
     * Mengubah payload lewat query mentah (melewati penjagaan model) harus
     * terbaca sebagai rantai yang rusak.
     */
    public function test_a_tampered_row_fails_chain_verification(): void
    {
        $this->itemFor(Client::factory()->create());

        DB::table('audit_logs')
            ->update(['event' => 'disamarkan']);

        $this->assertFalse(AuditLog::query()->sole()->verifyChain());
    }

    private function itemFor(Client $client): InventoryItem
    {
        return InventoryItem::factory()->create([
            'client_id' => $client->getKey(),
            'order_id' => Order::factory()->create(['client_id' => $client->getKey()])->getKey(),
        ]);
    }
}
