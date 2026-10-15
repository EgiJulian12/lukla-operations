<?php

namespace Tests\Feature\Order;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\QcResult;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Exceptions\InvalidOrderState;
use App\Models\Article;
use App\Models\Assembly;
use App\Models\Client;
use App\Models\Component;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OutputEntry;
use App\Models\Sample;
use App\Models\SampleRevision;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use App\Services\Order\OrderQuotationService;
use App\Services\Production\ProductionService;
use App\Services\Quality\QcService;
use App\Services\Sample\SampleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian Siklus Hidup dan State Machine Order (Modul 10A, Requirement 10, FR-004, FR-021).
 *
 * Alur lifecycle:
 * REQUESTED -> QUOTED -> QUOTE_APPROVED -> CONFIRMED -> READY_FOR_PRODUCTION
 * -> IN_PRODUCTION -> IN_QC -> COMPLETED -> CLOSED (atau CANCELLED).
 */
class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private User $owner;

    private User $lead;

    private User $tailor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->client = Client::factory()->create();

        $this->owner = User::factory()->create(['name' => 'Pak Owner']);
        $this->owner->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $this->lead = User::factory()->create(['name' => 'Mandor Budi']);
        $this->lead->assignRole(UserRole::PRODUCTION_LEAD->value);

        $this->tailor = User::factory()->create(['name' => 'Siti Penjahit']);
        $this->tailor->assignRole(UserRole::TAILOR_OPERATOR->value);
    }

    public function test_client_request_initializes_with_requested_status(): void
    {
        $service = app(OrderQuotationService::class);

        $order = $service->submitRequest(
            client: $this->client,
            designPaths: ['designs/sample.png'],
            description: 'Kemeja PDL 100 pcs',
        );

        $this->assertSame(OrderStatus::REQUESTED, $order->status);
        $this->assertFalse($order->isLocked());
        $this->assertTrue($order->canTransitionTo(OrderStatus::QUOTED));
        $this->assertFalse($order->canTransitionTo(OrderStatus::IN_PRODUCTION));
    }

    public function test_owner_quote_transitions_to_quoted_status(): void
    {
        $service = app(OrderQuotationService::class);
        $order = $service->submitRequest($this->client, ['designs/sample.png'], 'Kemeja PDL');

        $quoted = $service->provideQuote($order, 15000000, 'Harga sudah termasuk bahan', $this->owner);

        $this->assertSame(OrderStatus::QUOTED, $quoted->status);
        $this->assertTrue($quoted->canTransitionTo(OrderStatus::QUOTE_APPROVED));
    }

    public function test_client_approves_quote_transitions_to_quote_approved_status(): void
    {
        $service = app(OrderQuotationService::class);
        $order = $service->submitRequest($this->client, ['designs/sample.png'], 'Kemeja PDL');
        $quoted = $service->provideQuote($order, 15000000, 'Harga fix', $this->owner);

        $approved = $service->approveQuote($quoted);

        $this->assertSame(OrderStatus::QUOTE_APPROVED, $approved->status);
        $this->assertTrue($approved->canTransitionTo(OrderStatus::CONFIRMED));
    }

    public function test_approved_sample_conversion_initializes_order_as_confirmed_and_transitions_to_ready(): void
    {
        $article = Article::factory()->create(['client_id' => $this->client->getKey()]);
        $sample = Sample::factory()->create([
            'client_id' => $this->client->getKey(),
            'article_id' => $article->getKey(),
        ]);
        $revision = SampleRevision::factory()->create([
            'sample_id' => $sample->getKey(),
        ]);

        $sampleService = app(SampleService::class);
        $sampleService->submitForReview($revision);
        $sampleService->approveRevision($revision, $this->owner);

        $order = $sampleService->convertToOrder(
            sample: $sample->refresh(),
            scheme: WorkScheme::CMT,
            quantity: 50,
            dueDate: now()->addWeeks(2),
        );

        $this->assertSame(OrderStatus::CONFIRMED, $order->status);
        $this->assertTrue($order->canTransitionTo(OrderStatus::READY_FOR_PRODUCTION));

        $order->transitionTo(OrderStatus::READY_FOR_PRODUCTION);
        $this->assertSame(OrderStatus::READY_FOR_PRODUCTION, $order->refresh()->status);
    }

    public function test_starting_production_transitions_order_from_ready_to_in_production(): void
    {
        $article = Article::factory()->create(['client_id' => $this->client->getKey()]);
        $component = Component::create([
            'article_id' => $article->getKey(),
            'code' => 'KOMP-01',
            'name' => 'Badan',
            'sequence' => 1,
        ]);

        $order = Order::factory()->create([
            'client_id' => $this->client->getKey(),
            'article_id' => $article->getKey(),
            'status' => OrderStatus::READY_FOR_PRODUCTION,
        ]);

        $this->actingAs($this->lead);

        $running = app(ProductionService::class)->startProduction($order, [
            ['component_id' => $component->getKey(), 'user_id' => $this->tailor->getKey()],
        ]);

        $this->assertSame(OrderStatus::IN_PRODUCTION, $running->status);
    }

    public function test_assembling_full_order_quantity_transitions_order_to_in_qc(): void
    {
        $order = Order::factory()->create([
            'client_id' => $this->client->getKey(),
            'status' => OrderStatus::IN_PRODUCTION,
            'quantity' => 20,
        ]);

        $verified = OutputEntry::factory()->verified()->create([
            'order_id' => $order->getKey(),
            'client_id' => $this->client->getKey(),
            'quantity' => 20,
        ]);

        $this->actingAs($this->lead);

        $assembly = app(ProductionService::class)->assemble($order, [$verified->getKey()], 20);

        $this->assertSame(20, $assembly->quantity);
        $this->assertSame(OrderStatus::IN_QC, $order->refresh()->status);
    }

    public function test_recording_qc_inspection_on_in_production_assembly_transitions_order_to_in_qc(): void
    {
        $order = Order::factory()->create([
            'client_id' => $this->client->getKey(),
            'status' => OrderStatus::IN_PRODUCTION,
            'quantity' => 100,
        ]);

        $assembly = Assembly::factory()->create([
            'order_id' => $order->getKey(),
            'client_id' => $this->client->getKey(),
            'quantity' => 10,
        ]);

        $qc = app(QcService::class)->recordInspection(
            assembly: $assembly,
            result: QcResult::PASS,
            quantity: 10,
            inspector: $this->lead,
        );

        $this->assertSame(QcResult::PASS, $qc->result);
        $this->assertSame(OrderStatus::IN_QC, $order->refresh()->status);
    }

    public function test_when_one_hundred_percent_of_produced_units_pass_qc_order_transitions_to_completed(): void
    {
        $order = Order::factory()->create([
            'client_id' => $this->client->getKey(),
            'status' => OrderStatus::IN_QC,
            'quantity' => 50,
        ]);

        $assembly = Assembly::factory()->create([
            'order_id' => $order->getKey(),
            'client_id' => $this->client->getKey(),
            'quantity' => 50,
        ]);

        // Inspeksi pertama 30 unit PASS
        app(QcService::class)->recordInspection(
            assembly: $assembly,
            result: QcResult::PASS,
            quantity: 30,
            inspector: $this->lead,
        );

        $this->assertSame(OrderStatus::IN_QC, $order->refresh()->status);
        $this->assertNull($order->completed_at);

        // Inspeksi kedua 20 unit PASS -> total 50 unit PASS (100%)
        app(QcService::class)->recordInspection(
            assembly: $assembly,
            result: QcResult::PASS,
            quantity: 20,
            inspector: $this->lead,
        );

        $this->assertSame(OrderStatus::COMPLETED, $order->refresh()->status);
        $this->assertNotNull($order->completed_at);
        $this->assertTrue($order->isLocked());
    }

    public function test_when_completed_order_invoices_are_paid_order_transitions_to_closed(): void
    {
        $order = Order::factory()->create([
            'client_id' => $this->client->getKey(),
            'status' => OrderStatus::COMPLETED,
            'quantity' => 50,
        ]);

        $invoice = Invoice::factory()->issued()->create([
            'order_id' => $order->getKey(),
            'client_id' => $this->client->getKey(),
            'status' => InvoiceStatus::ISSUED,
        ]);

        $this->assertFalse($order->isEligibleForClosure());

        app(InvoiceService::class)->markPaid($invoice);

        $this->assertSame(OrderStatus::CLOSED, $order->refresh()->status);
        $this->assertTrue($order->isLocked());
    }

    public function test_invalid_status_transitions_are_prevented_with_exception(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::REQUESTED,
        ]);

        $this->expectException(InvalidOrderState::class);
        $this->expectExceptionMessage('tidak dapat berpindah status dari Permintaan Klien ke Dalam Produksi.');

        $order->transitionTo(OrderStatus::IN_PRODUCTION);
    }

    public function test_closed_orders_are_terminal_and_cannot_transition(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::CLOSED,
        ]);

        $this->assertTrue($order->isLocked());
        $this->assertFalse($order->canTransitionTo(OrderStatus::IN_PRODUCTION));

        $this->expectException(InvalidOrderState::class);
        $order->transitionTo(OrderStatus::CONFIRMED);
    }
}
