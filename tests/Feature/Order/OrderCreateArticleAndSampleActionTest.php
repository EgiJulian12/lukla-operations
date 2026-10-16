<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Enums\SampleStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Article;
use App\Models\Client;
use App\Models\Order;
use App\Models\Sample;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCreateArticleAndSampleActionTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->client = Client::factory()->create();
    }

    public function test_sample_team_can_see_and_execute_create_article_and_sample_action(): void
    {
        $timSample = $this->staffUser(UserRole::SAMPLE_TEAM);

        $order = Order::factory()->create([
            'client_id' => $this->client->id,
            'status' => OrderStatus::REQUESTED,
            'article_id' => null,
            'description' => 'Kemeja flanel lengan panjang',
            'order_number' => 'ORD-FLANEL-01',
        ]);

        $this->actingAs($timSample);

        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('createArticleAndSample', $order)
            ->callTableAction('createArticleAndSample', $order, [
                'name' => 'Kemeja Flanel v1',
                'code' => 'ART-FLANEL-01',
                'description' => 'Deskripsi artikel flanel',
            ]);

        $order->refresh();

        $this->assertNotNull($order->article_id);
        $this->assertNotNull($order->sample_id);

        $article = Article::find($order->article_id);
        $this->assertNotNull($article);
        $this->assertSame('Kemeja Flanel v1', $article->name);
        $this->assertSame('ART-FLANEL-01', $article->code);

        $sample = Sample::find($order->sample_id);
        $this->assertNotNull($sample);
        $this->assertSame(SampleStatus::DRAFT, $sample->status);
        $this->assertSame($article->id, $sample->article_id);
        $this->assertNotNull($sample->current_revision_id);
    }

    public function test_other_roles_cannot_see_create_article_and_sample_action(): void
    {
        $order = Order::factory()->create([
            'client_id' => $this->client->id,
            'status' => OrderStatus::REQUESTED,
            'article_id' => null,
        ]);

        $otherRoles = [
            UserRole::OWNER_EXECUTIVE,
            UserRole::PRODUCTION_LEAD,
            UserRole::FINANCE_PAYROLL,
            UserRole::WAREHOUSE,
            UserRole::QC_INSPECTOR,
            UserRole::TAILOR_OPERATOR,
        ];

        foreach ($otherRoles as $role) {
            $user = $this->staffUser($role);
            $this->assertFalse($user->can('createArticleAndSample', $order));
        }

        $owner = $this->staffUser(UserRole::OWNER_EXECUTIVE);
        $this->actingAs($owner);

        Livewire::test(ListOrders::class)
            ->assertTableActionHidden('createArticleAndSample', $order);
    }

    public function test_action_is_hidden_when_order_already_has_article(): void
    {
        $timSample = $this->staffUser(UserRole::SAMPLE_TEAM);
        $article = Article::factory()->create(['client_id' => $this->client->id]);

        $order = Order::factory()->create([
            'client_id' => $this->client->id,
            'status' => OrderStatus::REQUESTED,
            'article_id' => $article->id,
        ]);

        $this->actingAs($timSample);

        Livewire::test(ListOrders::class)
            ->assertTableActionHidden('createArticleAndSample', $order);
    }

    public function test_action_is_hidden_when_order_status_is_ineligible(): void
    {
        $timSample = $this->staffUser(UserRole::SAMPLE_TEAM);

        $ineligibleStatuses = [
            OrderStatus::READY_FOR_PRODUCTION,
            OrderStatus::IN_PRODUCTION,
            OrderStatus::IN_QC,
            OrderStatus::COMPLETED,
            OrderStatus::CANCELLED,
        ];

        foreach ($ineligibleStatuses as $status) {
            $order = Order::factory()->create([
                'client_id' => $this->client->id,
                'status' => $status,
                'article_id' => null,
            ]);

            $this->actingAs($timSample);

            Livewire::test(ListOrders::class)
                ->assertTableActionHidden('createArticleAndSample', $order);
        }
    }

    private function staffUser(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
