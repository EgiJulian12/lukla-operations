<?php

namespace Tests\Feature\Sample;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Samples\SampleResource;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Services\Sample\SampleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SampleDesignDownloadTest extends TestCase
{
    use RefreshDatabase;

    private SampleService $sampleService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->sampleService = app(SampleService::class);

        $disk = config('filesystems.default', 'local');
        Storage::fake($disk);
        if ($disk !== 's3') {
            Storage::fake('s3');
        }
    }

    public function test_user_can_view_design_description_and_images_on_sample_detail_page(): void
    {
        $client = Client::factory()->create(['name' => 'Brand Distro']);
        $disk = config('filesystems.default', 'local');

        UploadedFile::fake()->image('hoodie-front.png')->storeAs('orders/designs', 'hoodie-front.png', $disk);

        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'status' => OrderStatus::QUOTE_APPROVED,
            'description' => 'Hoodie oversized bahan fleece gramasi 330.',
            'design_images' => ['orders/designs/hoodie-front.png'],
        ]);

        $sample = $this->sampleService->initializeSampleFromOrder($order, [
            'name' => 'Hoodie Sample',
            'code' => 'SMP-HOODIE-01',
        ]);

        $sampleUser = $this->staffUser(UserRole::SAMPLE_TEAM);

        $this->actingAs($sampleUser)
            ->get(SampleResource::getUrl('edit', ['record' => $sample]))
            ->assertOk()
            ->assertSee('Desain & Acuan Permintaan Klien')
            ->assertSee('Hoodie oversized bahan fleece gramasi 330.')
            ->assertSee(route('samples.designs.download', $sample));
    }

    public function test_authorized_user_can_download_single_sample_design(): void
    {
        $client = Client::factory()->create();
        $disk = config('filesystems.default', 'local');

        UploadedFile::fake()->image('kaos.png')->storeAs('orders/designs', 'kaos.png', $disk);

        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'status' => OrderStatus::QUOTE_APPROVED,
            'design_images' => ['orders/designs/kaos.png'],
        ]);

        $sample = $this->sampleService->initializeSampleFromOrder($order, [
            'name' => 'Kaos Katun',
            'code' => 'SMP-KAOS-01',
        ]);

        $staff = $this->staffUser(UserRole::OWNER_EXECUTIVE);

        $response = $this->actingAs($staff)
            ->get(route('samples.designs.download', $sample));

        $response->assertOk();
        $this->assertTrue(
            str_contains((string) $response->headers->get('content-disposition'), 'kaos.png')
        );
    }

    public function test_authorized_user_can_download_multiple_designs_as_zip(): void
    {
        $client = Client::factory()->create();
        $disk = config('filesystems.default', 'local');

        UploadedFile::fake()->image('depan.png')->storeAs('orders/designs', 'depan.png', $disk);
        UploadedFile::fake()->image('belakang.png')->storeAs('orders/designs', 'belakang.png', $disk);

        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'status' => OrderStatus::QUOTE_APPROVED,
            'design_images' => ['orders/designs/depan.png', 'orders/designs/belakang.png'],
        ]);

        $sample = $this->sampleService->initializeSampleFromOrder($order, [
            'name' => 'Jaket Varsity',
            'code' => 'SMP-VARSITY',
        ]);

        $staff = $this->staffUser(UserRole::OWNER_EXECUTIVE);

        $response = $this->actingAs($staff)
            ->get(route('samples.designs.download', $sample));

        $response->assertOk();
        $this->assertTrue(
            str_contains((string) $response->headers->get('content-type'), 'zip')
                || str_contains((string) $response->headers->get('content-disposition'), 'desain-sample-SMP-VARSITY.zip')
        );
    }

    public function test_authorized_user_can_download_specific_design_by_index(): void
    {
        $client = Client::factory()->create();
        $disk = config('filesystems.default', 'local');

        UploadedFile::fake()->image('depan.png')->storeAs('orders/designs', 'depan.png', $disk);
        UploadedFile::fake()->image('belakang.png')->storeAs('orders/designs', 'belakang.png', $disk);

        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'status' => OrderStatus::QUOTE_APPROVED,
            'design_images' => ['orders/designs/depan.png', 'orders/designs/belakang.png'],
        ]);

        $sample = $this->sampleService->initializeSampleFromOrder($order, [
            'name' => 'Kemeja Flannel',
            'code' => 'SMP-FLANNEL',
        ]);

        $staff = $this->staffUser(UserRole::OWNER_EXECUTIVE);

        $response = $this->actingAs($staff)
            ->get(route('samples.designs.download', ['sample' => $sample, 'index' => 1]));

        $response->assertOk();
        $this->assertTrue(
            str_contains((string) $response->headers->get('content-disposition'), 'belakang.png')
        );
    }

    public function test_unauthorized_client_is_forbidden_from_downloading_sample_designs(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        $disk = config('filesystems.default', 'local');
        UploadedFile::fake()->image('rahasia.png')->storeAs('orders/designs', 'rahasia.png', $disk);

        $orderA = Order::factory()->create([
            'client_id' => $clientA->getKey(),
            'status' => OrderStatus::QUOTE_APPROVED,
            'design_images' => ['orders/designs/rahasia.png'],
        ]);

        $sampleA = $this->sampleService->initializeSampleFromOrder($orderA, [
            'name' => 'Produk A',
            'code' => 'SMP-A',
        ]);

        // User dari Klien B
        $userClientB = User::factory()->create(['client_id' => $clientB->getKey()]);
        $userClientB->assignRole(UserRole::CLIENT_MAKLON->value);

        $this->actingAs($userClientB)
            ->get(route('samples.designs.download', $sampleA))
            ->assertForbidden();
    }

    private function staffUser(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
