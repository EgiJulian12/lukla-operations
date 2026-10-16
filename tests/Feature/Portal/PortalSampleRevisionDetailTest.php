<?php

namespace Tests\Feature\Portal;

use App\Enums\UserRole;
use App\Filament\Portal\Resources\Samples\Pages\ViewPortalSample;
use App\Filament\Portal\Resources\Samples\RelationManagers\PortalRevisionsRelationManager;
use App\Filament\Resources\Samples\Pages\ViewSample;
use App\Filament\Resources\Samples\RelationManagers\RevisionsRelationManager;
use App\Models\Article;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Services\Sample\SampleService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PortalSampleRevisionDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $disk = config('filesystems.default', 'local');
        Storage::fake($disk);
        if ($disk !== 's3') {
            Storage::fake('s3');
        }
    }

    public function test_client_sees_design_images_and_description_in_revision_detail_modal(): void
    {
        $disk = config('filesystems.default', 'local');
        UploadedFile::fake()->image('tote-bag-front.jpg')->storeAs('orders/designs', 'tote-bag-front.jpg', $disk);

        $client = Client::factory()->create();
        $user = $this->clientUser($client);

        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'design_images' => ['orders/designs/tote-bag-front.jpg'],
            'description' => 'Tote bag kanvas warna navy dengan saku resleting',
        ]);

        $article = Article::factory()->create([
            'client_id' => $client->getKey(),
            'order_id' => $order->getKey(),
            'name' => 'Tote Bag Navy',
        ]);

        $sample = app(SampleService::class)->createSample($article, 'SMP-TOTE-01', 'Sample Tote Bag Navy');
        $v1 = app(SampleService::class)->addRevision(
            sample: $sample,
            specs: [
                'design_images' => $order->design_images,
                'description' => $order->description,
                'order_number' => $order->order_number,
                'Warna' => 'Navy',
            ],
            notes: 'Revisi perdana',
        );

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('portal'));

        Livewire::test(PortalRevisionsRelationManager::class, [
            'ownerRecord' => $sample,
            'pageClass' => ViewPortalSample::class,
        ])
            ->mountTableAction('viewRevision', $v1)
            ->assertTableActionDataSet(function (array $rawState): array {
                $this->assertContains('orders/designs/tote-bag-front.jpg', array_values($rawState['design_images'] ?? []));
                $this->assertSame('Tote bag kanvas warna navy dengan saku resleting', $rawState['design_description'] ?? null);

                return [];
            });
    }

    public function test_client_sees_material_usage_items_in_revision_detail_modal(): void
    {
        $client = Client::factory()->create();
        $user = $this->clientUser($client);

        $article = Article::factory()->create(['client_id' => $client->getKey()]);
        $sample = app(SampleService::class)->createSample($article, 'SMP-JACKET-01', 'Sample Jaket Canvas');

        $v1 = app(SampleService::class)->addRevision(
            sample: $sample,
            specs: ['Warna' => 'Hitam'],
            materialUsage: [
                'Kain Canvas 12oz' => '1.5 meter',
                'Resleting Besi YKK' => '1 pcs',
            ],
        );

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('portal'));

        Livewire::test(PortalRevisionsRelationManager::class, [
            'ownerRecord' => $sample,
            'pageClass' => ViewPortalSample::class,
        ])
            ->mountTableAction('viewRevision', $v1)
            ->assertTableActionDataSet(function (array $rawState): array {
                $materialItems = array_values($rawState['material_usage_items'] ?? []);

                $this->assertEquals([
                    [
                        'item_name' => 'Kain Canvas 12oz',
                        'qty' => 1.5,
                        'uom' => 'meter',
                    ],
                    [
                        'item_name' => 'Resleting Besi YKK',
                        'qty' => 1.0,
                        'uom' => 'pcs',
                    ],
                ], $materialItems);

                return [];
            });
    }

    public function test_internal_sample_team_can_save_material_usage_without_losing_it(): void
    {
        $client = Client::factory()->create();
        $operator = User::factory()->create(['client_id' => null]);
        $operator->assignRole(UserRole::SAMPLE_TEAM->value);

        $article = Article::factory()->create(['client_id' => $client->getKey()]);
        $sample = app(SampleService::class)->createSample($article, 'SMP-EDIT-01', 'Sample Edit Bahan');

        $revision = app(SampleService::class)->addRevision(
            sample: $sample,
            specs: [
                'design_images' => ['orders/designs/mockup.png'],
                'description' => 'Deskripsi order mockup',
                'Bahan Utama' => 'Katun Combed 30s',
            ],
            notes: 'Draft sample awal',
        );

        $this->actingAs($operator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(RevisionsRelationManager::class, [
            'ownerRecord' => $sample,
            'pageClass' => ViewSample::class,
        ])
            ->assertTableActionVisible('editRevision', $revision)
            ->callTableAction('editRevision', $revision, data: [
                'specs' => [
                    'Bahan Utama' => 'Katun Combed 24s',
                ],
                'material_usage_items' => [
                    [
                        'item_name' => 'Kain Katun Combed 24s',
                        'qty' => 1.25,
                        'uom' => 'meter',
                    ],
                    [
                        'item_name' => 'Rib Leher',
                        'qty' => 10,
                        'uom' => 'cm',
                    ],
                ],
                'component_time_studies' => [
                    [
                        'component_name' => 'Jahit Badan',
                        'duration_seconds' => 120,
                        'notes' => 'Standar',
                    ],
                ],
                'notes' => 'Catatan pengerjaan update',
            ])
            ->assertHasNoTableActionErrors();

        $revision->refresh();

        $this->assertSame([
            'Kain Katun Combed 24s' => '1.25 meter',
            'Rib Leher' => '0.1 meter',
        ], $revision->material_usage);

        $this->assertSame('Catatan pengerjaan update', $revision->notes);
        $this->assertSame(['orders/designs/mockup.png'], $revision->specs['design_images']);
        $this->assertSame('Deskripsi order mockup', $revision->specs['description']);
        $this->assertSame('Katun Combed 24s', $revision->specs['Bahan Utama']);

        // Pastikan tetap dapat diupdate saat IN_REVIEW jika bahan baku perlu dilengkapi
        app(SampleService::class)->submitForReview($revision);
        $revision->refresh();

        Livewire::test(RevisionsRelationManager::class, [
            'ownerRecord' => $sample,
            'pageClass' => ViewSample::class,
        ])
            ->assertTableActionVisible('editRevision', $revision)
            ->callTableAction('editRevision', $revision, data: [
                'specs' => [
                    'Bahan Utama' => 'Katun Combed 24s',
                ],
                'material_usage_items' => [
                    [
                        'item_name' => 'Kain Katun Combed 24s',
                        'qty' => 1.5,
                        'uom' => 'meter',
                    ],
                ],
                'notes' => 'Update bahan saat in review',
            ])
            ->assertHasNoTableActionErrors();

        $revision->refresh();
        $this->assertSame([
            'Kain Katun Combed 24s' => '1.5 meter',
        ], $revision->material_usage);
    }

    public function test_portal_view_sample_page_displays_material_usage_on_infolist(): void
    {
        $client = Client::factory()->create();
        $user = $this->clientUser($client);

        $article = Article::factory()->create(['client_id' => $client->getKey()]);
        $sample = app(SampleService::class)->createSample($article, 'SMP-PORTAL-01', 'Sample Tote Bag');

        app(SampleService::class)->addRevision(
            sample: $sample,
            specs: ['Bahan Elastis' => 'Tidak Mudah Rusak'],
            materialUsage: [
                'Kain Kanvas Biru' => '0.8 meter',
                'Resleting YKK' => '1 pcs',
            ],
        );

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        Filament::setTenant($client);

        Livewire::test(ViewPortalSample::class, [
            'record' => $sample->getRouteKey(),
        ])
            ->assertSee('Estimasi Pemakaian Bahan Baku & Aksesoris (Per 1 Pcs)')
            ->assertSee('Kain Kanvas Biru')
            ->assertSee('0.8 meter')
            ->assertSee('Resleting YKK')
            ->assertSee('1 pcs');
    }

    private function clientUser(Client $client): User
    {
        $user = User::factory()->create(['client_id' => $client->getKey()]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        return $user;
    }
}
