<?php

namespace Tests\Feature\Production;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\IncompleteComponentAssignment;
use App\Exceptions\InvalidOrderState;
use App\Filament\Resources\ProductionOrders\Pages\ListProductionOrders;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Models\Article;
use App\Models\Client;
use App\Models\Component;
use App\Models\Order;
use App\Models\OutputEntry;
use App\Models\User;
use App\Services\Production\ProductionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Antrean kerja Kepala Produksi (FR-004 → FR-009).
 *
 * Antrean menampilkan order yang berada di lantai produksi: Siap Produksi,
 * Dalam Produksi, dan Dalam QC. Memulai produksi sekaligus menetapkan penjahit
 * untuk setiap komponen artikel, sehingga order tidak pernah berjalan tanpa
 * pemilik komponen yang jelas.
 */
class ProductionOrderQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $productionLead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->productionLead = User::factory()->create();
        $this->productionLead->assignRole(UserRole::PRODUCTION_LEAD->value);
    }

    public function test_the_queue_lists_the_orders_currently_on_the_production_floor(): void
    {
        $ready = Order::factory()->create(['status' => OrderStatus::READY_FOR_PRODUCTION]);
        $running = Order::factory()->create(['status' => OrderStatus::IN_PRODUCTION]);
        $inQc = Order::factory()->create(['status' => OrderStatus::IN_QC]);

        $awaitingQuote = Order::factory()->create(['status' => OrderStatus::REQUESTED]);
        $draft = Order::factory()->create(['status' => OrderStatus::DRAFT]);
        $confirmed = Order::factory()->create(['status' => OrderStatus::CONFIRMED]);
        $completed = Order::factory()->create(['status' => OrderStatus::COMPLETED]);
        $cancelled = Order::factory()->create(['status' => OrderStatus::CANCELLED]);

        $records = ProductionOrderResource::getEloquentQuery()->get();

        $this->assertTrue($records->contains($ready));
        $this->assertTrue($records->contains($running));
        $this->assertTrue($records->contains($inQc));

        $this->assertFalse($records->contains($awaitingQuote));
        $this->assertFalse($records->contains($draft));
        $this->assertFalse($records->contains($confirmed));
        $this->assertFalse($records->contains($completed));
        $this->assertFalse($records->contains($cancelled));
    }

    public function test_starting_production_records_a_tailor_for_every_component(): void
    {
        $order = $this->readyOrder(['Kerah', 'Lengan']);
        [$kerah, $lengan] = $order->components()->all();

        $sewer = $this->tailor('Siti Penjahit');
        $secondSewer = $this->tailor('Budi Penjahit');

        $this->actingAs($this->productionLead);

        Livewire::test(ListProductionOrders::class)
            ->assertCanSeeTableRecords([$order])
            ->assertTableActionVisible('startProduction', $order)
            ->callTableAction('startProduction', $order, [
                'assignments' => [
                    $kerah->getKey() => $sewer->getKey(),
                    $lengan->getKey() => $secondSewer->getKey(),
                ],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::IN_PRODUCTION, $order->refresh()->status);

        $plan = $order->componentAssignments()->get();

        $this->assertCount(2, $plan);
        $this->assertSame($sewer->getKey(), (int) $plan->firstWhere('component_id', $kerah->getKey())->user_id);
        $this->assertSame($secondSewer->getKey(), (int) $plan->firstWhere('component_id', $lengan->getKey())->user_id);
    }

    public function test_the_queue_shows_the_tailor_holding_each_component(): void
    {
        $order = $this->readyOrder(['Kerah']);
        [$kerah] = $order->components()->all();

        $sewer = $this->tailor('Siti Penjahit');

        app(ProductionService::class)->startProduction($order, [
            ['component_id' => $kerah->getKey(), 'user_id' => $sewer->getKey()],
        ]);

        $this->actingAs($this->productionLead);

        Livewire::test(ListProductionOrders::class)
            ->assertCanSeeTableRecords([$order])
            ->assertSee('Kerah: Siti Penjahit');
    }

    public function test_a_tailor_holds_only_the_components_assigned_to_them(): void
    {
        $order = $this->readyOrder(['Kerah', 'Lengan']);
        [$kerah, $lengan] = $order->components()->all();

        $sewer = $this->tailor('Siti Penjahit');

        app(ProductionService::class)->startProduction($order, [
            ['component_id' => $kerah->getKey(), 'user_id' => $sewer->getKey()],
            ['component_id' => $lengan->getKey(), 'user_id' => $this->tailor('Budi Penjahit')->getKey()],
        ]);

        // Inilah daftar yang ditawarkan form output 30 menit kepada penjahit:
        // komponennya sendiri, bukan seluruh komponen order.
        $this->assertTrue($order->hasComponentPlan());
        $this->assertSame([$kerah->getKey()], $order->componentsAssignedTo($sewer)->pluck('id')->all());
    }

    public function test_production_cannot_start_before_every_component_has_a_tailor(): void
    {
        $order = $this->readyOrder(['Kerah', 'Lengan']);
        [$kerah] = $order->components()->all();

        $sewer = $this->tailor('Siti Penjahit');

        try {
            app(ProductionService::class)->startProduction($order, [
                ['component_id' => $kerah->getKey(), 'user_id' => $sewer->getKey()],
            ]);

            $this->fail('Produksi seharusnya ditolak selama ada komponen tanpa penjahit.');
        } catch (IncompleteComponentAssignment $exception) {
            $this->assertStringContainsString('Lengan', $exception->getMessage());
        }

        // Ditolak sebelum apa pun ditulis: order tetap Siap Produksi dan tidak
        // ada rencana separuh jadi yang tertinggal.
        $this->assertSame(OrderStatus::READY_FOR_PRODUCTION, $order->refresh()->status);
        $this->assertCount(0, $order->componentAssignments);
    }

    public function test_a_component_from_another_article_cannot_be_assigned(): void
    {
        $order = $this->readyOrder(['Kerah']);
        $foreignComponent = Component::factory()->create();
        $sewer = $this->tailor('Siti Penjahit');

        $this->expectException(InvalidArgumentException::class);

        app(ProductionService::class)->startProduction($order, [
            ['component_id' => $foreignComponent->getKey(), 'user_id' => $sewer->getKey()],
        ]);
    }

    public function test_only_tailors_can_hold_a_component(): void
    {
        $order = $this->readyOrder(['Kerah']);
        [$kerah] = $order->components()->all();

        $inspector = User::factory()->create();
        $inspector->assignRole(UserRole::QC_INSPECTOR->value);

        $this->expectException(InvalidArgumentException::class);

        app(ProductionService::class)->startProduction($order, [
            ['component_id' => $kerah->getKey(), 'user_id' => $inspector->getKey()],
        ]);
    }

    public function test_the_order_knows_which_tailors_logged_output(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::IN_PRODUCTION]);

        $sewer = $this->tailor('Siti Penjahit');

        // Dua catatan output pada komponen berbeda: penjahit yang sama hanya
        // dihitung sekali.
        OutputEntry::factory()->count(2)->create([
            'order_id' => $order->getKey(),
            'client_id' => $order->client_id,
            'user_id' => $sewer->getKey(),
        ]);

        $this->assertSame([$sewer->getKey()], $order->operators->pluck('id')->all());
    }

    public function test_the_queue_is_reserved_for_the_production_lead(): void
    {
        $this->actingAs($this->productionLead);
        $this->assertTrue(ProductionOrderResource::canAccess());

        foreach ([UserRole::SAMPLE_TEAM, UserRole::QC_INSPECTOR, UserRole::WAREHOUSE, UserRole::OWNER_EXECUTIVE] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role->value);

            $this->actingAs($user);

            $this->assertFalse(ProductionOrderResource::canAccess(), "Peran {$role->value} tidak boleh membuka antrean produksi.");
            $this->get(ProductionOrderResource::getUrl('index'))->assertForbidden();
        }
    }

    public function test_only_the_production_lead_may_start_production(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::READY_FOR_PRODUCTION]);

        $this->assertTrue($this->productionLead->can('startProduction', $order));

        $sampleTeam = User::factory()->create();
        $sampleTeam->assignRole(UserRole::SAMPLE_TEAM->value);

        $this->assertFalse($sampleTeam->can('startProduction', $order));
    }

    public function test_production_cannot_start_for_an_order_that_is_not_ready(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::DRAFT]);

        $this->expectException(InvalidOrderState::class);

        app(ProductionService::class)->startProduction($order, []);
    }

    /**
     * Order siap produksi yang artikelnya sudah dipecah menjadi komponen.
     *
     * @param  array<int, string>  $componentNames
     */
    private function readyOrder(array $componentNames): Order
    {
        $client = Client::factory()->create();
        $article = Article::factory()->create(['client_id' => $client->getKey()]);

        foreach ($componentNames as $index => $name) {
            Component::create([
                'article_id' => $article->getKey(),
                'code' => sprintf('KOMP-%02d', $index + 1),
                'name' => $name,
                'sequence' => $index + 1,
            ]);
        }

        return Order::factory()->create([
            'client_id' => $client->getKey(),
            'article_id' => $article->getKey(),
            'status' => OrderStatus::READY_FOR_PRODUCTION,
        ]);
    }

    private function tailor(string $name): User
    {
        $tailor = User::factory()->create(['name' => $name]);
        $tailor->assignRole(UserRole::TAILOR_OPERATOR->value);

        return $tailor;
    }
}
