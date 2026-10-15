<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Exceptions\InvalidOrderQuotationState;
use App\Filament\Portal\Resources\Orders\Pages\CreatePortalOrder;
use App\Filament\Portal\Resources\Orders\Pages\ViewPortalOrder;
use App\Filament\Portal\Resources\Orders\PortalOrderResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Article;
use App\Models\Client;
use App\Models\Order;
use App\Models\Sample;
use App\Models\User;
use App\Services\Order\OrderQuotationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Alur positif permintaan klien: ajukan design → ditawar owner → disetujui
 * klien → owner mengirim permintaan sample.
 */
class ClientOrderQuotationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $disk = $this->getTestDisk();
        Storage::fake($disk);
        if ($disk !== 's3') {
            Storage::fake('s3');
        }
    }

    public function test_a_client_request_keeps_the_design_and_the_clients_own_identity(): void
    {
        $client = Client::factory()->create();
        $requester = $this->clientUser($client);

        $order = $this->service()->submitRequest(
            $client,
            ['orders/designs/kaos-1.png', 'orders/designs/kaos-2.png'],
            'Kaos raglan lengan panjang, bahan katun combed 30s.',
            null,
            $requester,
        );

        $this->assertSame(OrderStatus::REQUESTED, $order->status);
        $this->assertSame($client->getKey(), $order->client_id);
        $this->assertNull($order->quantity);
        $this->assertSame([
            'orders/designs/kaos-1.png',
            'orders/designs/kaos-2.png',
        ], $order->design_images);
        $this->assertStringStartsWith('REQ-', $order->order_number);
    }

    public function test_client_request_can_include_order_quantity_and_confirm_it_upon_quote_approval(): void
    {
        $client = Client::factory()->create();
        $requester = $this->clientUser($client);

        // Klien mengisi jumlah order saat submit request pertama kali
        $order = $this->service()->submitRequest(
            client: $client,
            designPaths: ['orders/designs/jersey-front.png'],
            description: 'Jersey Futsal Dryfit 24s',
            requester: $requester,
            quantity: 250,
        );

        $this->assertSame(OrderStatus::REQUESTED, $order->status);
        $this->assertSame(250, $order->quantity);

        // Owner memberi penawaran
        $owner = $this->staffUser(UserRole::OWNER_EXECUTIVE);
        $this->service()->provideQuote($order, 18750000.0, 'Harga per pcs 75.000 untuk 250 pcs.', $owner);

        // Klien menyetujui penawaran dengan mengonfirmasi atau menyesuaikan jumlah order
        $approved = $this->service()->approveQuote(
            order: $order,
            client: $requester,
            quantity: 300,
            notes: 'Tambah 50 pcs jadi total 300 pcs',
        );

        $this->assertSame(OrderStatus::QUOTE_APPROVED, $approved->status);
        $this->assertSame(300, $approved->quantity);
        $this->assertSame('Tambah 50 pcs jadi total 300 pcs', $approved->notes);
    }

    public function test_requests_from_different_clients_get_distinct_order_numbers(): void
    {
        $first = $this->service()->submitRequest(Client::factory()->create(), [], 'Model A');
        $second = $this->service()->submitRequest(Client::factory()->create(), [], 'Model B');

        $this->assertNotSame($first->order_number, $second->order_number);
    }

    public function test_an_owner_quote_moves_the_request_to_awaiting_client_approval(): void
    {
        $order = $this->requestedOrder();
        $owner = $this->staffUser(UserRole::OWNER_EXECUTIVE);

        $quoted = $this->service()->provideQuote($order, 12500000.0, 'Termasuk bahan dan finishing.', $owner);

        $this->assertSame(OrderStatus::QUOTED, $quoted->status);
        $this->assertSame('12500000.00', $quoted->estimated_cost);
        $this->assertSame('Termasuk bahan dan finishing.', $quoted->quote_notes);
        $this->assertSame($owner->getKey(), $quoted->quoted_by);
        $this->assertNotNull($quoted->quoted_at);
    }

    public function test_the_client_approves_the_quote_to_open_the_sample_step(): void
    {
        $client = Client::factory()->create();
        $clientUser = $this->clientUser($client);
        $order = $this->requestedOrder($client);
        $this->service()->provideQuote($order, 5000000.0, null, $this->staffUser(UserRole::OWNER_EXECUTIVE));

        $approved = $this->service()->approveQuote($order, $clientUser);

        $this->assertSame(OrderStatus::QUOTE_APPROVED, $approved->status);
        $this->assertNotNull($approved->quote_approved_at);
    }

    public function test_requesting_a_sample_creates_a_sample_carrying_the_clients_design(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder($client, ['orders/designs/polo.png'], 'Polo shirt bordir logo.');
        $this->service()->provideQuote($order, 7500000.0, 'Harga per pcs 75rb.', $this->staffUser(UserRole::OWNER_EXECUTIVE));
        $this->service()->approveQuote($order, $this->clientUser($client));

        $sample = $this->service()->requestSample($order, $this->staffUser(UserRole::OWNER_EXECUTIVE));

        $this->assertInstanceOf(Sample::class, $sample);
        $this->assertSame($client->getKey(), $sample->client_id);
        $this->assertSame($order->order_number, $sample->code);

        $revision = $sample->currentRevision;
        $this->assertNotNull($revision);
        $this->assertSame(['orders/designs/polo.png'], $revision->specs['design_images']);
        $this->assertSame('Polo shirt bordir logo.', $revision->specs['description']);

        $order->refresh();
        $this->assertSame($sample->getKey(), $order->sample_id);
        $this->assertNotNull($order->sample_requested_at);
    }

    public function test_requesting_a_sample_reuses_an_article_the_client_already_chose(): void
    {
        $client = Client::factory()->create();
        $article = Article::factory()->create(['client_id' => $client->getKey()]);
        $order = $this->requestedOrder($client);
        $order->update(['article_id' => $article->getKey()]);
        $this->service()->provideQuote($order, 1000000.0, null, $this->staffUser(UserRole::OWNER_EXECUTIVE));
        $this->service()->approveQuote($order, $this->clientUser($client));

        $sample = $this->service()->requestSample($order, $this->staffUser(UserRole::OWNER_EXECUTIVE));

        $this->assertSame($article->getKey(), $sample->article_id);
    }

    public function test_a_quote_cannot_be_given_twice(): void
    {
        $order = $this->requestedOrder();
        $owner = $this->staffUser(UserRole::OWNER_EXECUTIVE);
        $this->service()->provideQuote($order, 1000.0, null, $owner);

        $this->expectException(InvalidOrderQuotationState::class);

        $this->service()->provideQuote($order, 2000.0, null, $owner);
    }

    public function test_a_quote_cannot_be_approved_before_the_owner_quotes(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder($client);

        $this->expectException(InvalidOrderQuotationState::class);

        $this->service()->approveQuote($order, $this->clientUser($client));
    }

    public function test_a_sample_request_is_rejected_until_the_quote_is_approved(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder($client);
        $this->service()->provideQuote($order, 1000.0, null, $this->staffUser(UserRole::OWNER_EXECUTIVE));

        $this->expectException(InvalidOrderQuotationState::class);

        $this->service()->requestSample($order, $this->staffUser(UserRole::OWNER_EXECUTIVE));
    }

    public function test_a_sample_request_cannot_be_sent_twice(): void
    {
        $client = Client::factory()->create();
        $order = $this->approvedOrder($client);
        $owner = $this->staffUser(UserRole::OWNER_EXECUTIVE);
        $this->service()->requestSample($order, $owner);

        $this->expectException(InvalidOrderQuotationState::class);

        $this->service()->requestSample($order->refresh(), $owner);
    }

    public function test_a_rejected_quote_returns_the_request_to_the_owner(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder($client);
        $this->service()->provideQuote($order, 9000000.0, 'Harga awal.', $this->staffUser(UserRole::OWNER_EXECUTIVE));

        $rejected = $this->service()->rejectQuote($order, 'Terlalu mahal, mohon ditinjau.');

        $this->assertSame(OrderStatus::REQUESTED, $rejected->status);
        $this->assertStringContainsString('Terlalu mahal', $rejected->quote_notes);
    }

    public function test_only_the_owner_can_quote_and_dispatch_the_sample_request(): void
    {
        $order = $this->requestedOrder();
        $operator = $this->staffUser(UserRole::TAILOR_OPERATOR);
        $inspector = $this->staffUser(UserRole::QC_INSPECTOR);

        $this->assertFalse($operator->can('quote', $order));
        $this->assertFalse($inspector->can('quote', $order));
        $this->assertFalse($operator->can('requestSample', $order));
    }

    public function test_a_client_only_approves_its_own_order(): void
    {
        $mine = Client::factory()->create();
        $other = Client::factory()->create();

        $order = $this->requestedOrder($mine);
        $this->service()->provideQuote($order, 1000.0, null, $this->staffUser(UserRole::OWNER_EXECUTIVE));

        $this->assertFalse($this->clientUser($other)->can('approveQuote', $order));
        $this->assertTrue($this->clientUser($mine)->can('approveQuote', $order));
    }

    public function test_an_internal_role_cannot_approve_a_quote_on_the_clients_behalf(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder($client);
        $this->service()->provideQuote($order, 1000.0, null, $this->staffUser(UserRole::OWNER_EXECUTIVE));

        $this->assertFalse($this->staffUser(UserRole::OWNER_EXECUTIVE)->can('approveQuote', $order));
    }

    public function test_the_order_exposes_design_image_urls_from_the_s3_disk(): void
    {
        $order = $this->requestedOrder(designImages: ['orders/designs/a.png']);

        $this->assertStringContainsString('orders/designs/a.png', $order->designImageUrls()[0]);
    }

    public function test_a_client_can_open_the_new_request_page(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->clientUser($client));

        $this->get(CreatePortalOrder::getUrl(panel: 'portal', tenant: $client))
            ->assertOk();
    }

    /**
     * Tombol pengajuan harus benar-benar tampil di daftar, bukan hanya
     * rutenya ada — Filament 5 tidak menambahkannya otomatis.
     */
    public function test_the_portal_order_list_shows_the_request_button(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->clientUser($client));

        $this->get(PortalOrderResource::getUrl(panel: 'portal', tenant: $client))
            ->assertOk()
            ->assertSee('Ajukan Pesanan')
            ->assertSee(CreatePortalOrder::getUrl(panel: 'portal', tenant: $client), escape: false);
    }

    public function test_the_owner_can_open_the_order_list_with_quotation_actions(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder($client);
        $this->service()->provideQuote($order, 4000000.0, 'Catatan.', $this->staffUser(UserRole::OWNER_EXECUTIVE));

        $this->actingAs($this->staffUser(UserRole::OWNER_EXECUTIVE));

        $this->get(OrderResource::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_a_client_can_open_the_portal_list_showing_the_quote(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder($client);
        $this->service()->provideQuote($order, 4000000.0, 'Catatan.', $this->staffUser(UserRole::OWNER_EXECUTIVE));

        $this->actingAs($this->clientUser($client));

        $this->get(PortalOrderResource::getUrl(panel: 'portal', tenant: $client))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    /**
     * Bucket s3 privat, jadi URL polos ditolak 403 dan pratinjau gambar tampak
     * kosong; gambar harus dirender lewat URL bertanda tangan.
     */
    public function test_the_order_view_renders_the_design_image_through_a_signed_url(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder($client, ['orders/designs/kaos.png']);

        $disk = $this->getTestDisk();
        Storage::disk($disk)->put('orders/designs/kaos.png', 'dummy');
        Storage::disk($disk)->buildTemporaryUrlsUsing(
            fn (string $path): string => 'https://signed.example/'.$path,
        );

        $this->actingAs($this->clientUser($client));

        $this->get(ViewPortalOrder::getUrl(['record' => $order], panel: 'portal', tenant: $client))
            ->assertOk()
            ->assertSee('https://signed.example/orders/designs/kaos.png', escape: false);
    }

    /**
     * Owner harus melihat permintaan asli klien di modal penawaran, bukan
     * menebak biaya dari kolom tabel yang terpotong.
     */
    public function test_the_quote_modal_shows_the_clients_design_and_description(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder(
            $client,
            ['orders/designs/kaos.png'],
            'Kaos raglan lengan panjang, bahan katun combed 30s.',
        );

        $disk = $this->getTestDisk();
        Storage::disk($disk)->put('orders/designs/kaos.png', 'dummy');
        Storage::disk($disk)->buildTemporaryUrlsUsing(
            fn (string $path): string => 'https://signed.example/'.$path,
        );

        $this->actingAs($this->staffUser(UserRole::OWNER_EXECUTIVE));

        Livewire::test(ListOrders::class)
            ->mountTableAction('provideQuote', $order)
            ->assertMountedActionModalSee('Kaos raglan lengan panjang, bahan katun combed 30s.')
            ->assertMountedActionModalSee('https://signed.example/orders/designs/kaos.png', escape: false);
    }

    /**
     * Pratinjau itu murni tampilan: mengirim penawaran harus tetap menyimpan
     * biaya dan catatan, bukan data permintaan klien.
     */
    public function test_the_quote_preview_does_not_leak_into_the_submitted_quote(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder($client, ['orders/designs/kaos.png'], 'Model dasar.');

        $this->actingAs($this->staffUser(UserRole::OWNER_EXECUTIVE));

        Livewire::test(ListOrders::class)
            ->callTableAction('provideQuote', $order, data: [
                'estimated_cost' => 12500000,
                'quote_notes' => 'Termasuk bahan dan finishing.',
            ])
            ->assertHasNoTableActionErrors();

        $order->refresh();

        $this->assertSame(OrderStatus::QUOTED, $order->status);
        $this->assertSame('12500000.00', $order->estimated_cost);
        $this->assertSame('Termasuk bahan dan finishing.', $order->quote_notes);
        $this->assertSame(['orders/designs/kaos.png'], $order->design_images);
    }

    private function service(): OrderQuotationService
    {
        return app(OrderQuotationService::class);
    }

    /**
     * @param  array<int, string>  $designImages
     */
    private function requestedOrder(
        ?Client $client = null,
        array $designImages = [],
        string $description = 'Model dasar.',
    ): Order {
        return $this->service()->submitRequest(
            $client ?? Client::factory()->create(),
            $designImages,
            $description,
        );
    }

    private function approvedOrder(Client $client): Order
    {
        $order = $this->requestedOrder($client);
        $this->service()->provideQuote($order, 1000.0, null, $this->staffUser(UserRole::OWNER_EXECUTIVE));
        $this->service()->approveQuote($order, $this->clientUser($client));

        return $order->refresh();
    }

    public function test_order_request_can_be_submitted_with_work_scheme_and_due_date(): void
    {
        $client = Client::factory()->create();
        $requester = $this->clientUser($client);

        $order = $this->service()->submitRequest(
            $client,
            ['orders/designs/jacket.png'],
            'Jaket parasut outdoor.',
            null,
            $requester,
            WorkScheme::SEMI_CMT,
            '2026-11-20',
        );

        $this->assertSame(WorkScheme::SEMI_CMT, $order->work_scheme);
        $this->assertSame('2026-11-20', $order->due_date->format('Y-m-d'));
    }

    public function test_quote_approval_can_update_work_scheme_due_date_and_notes(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder($client);
        $this->service()->provideQuote($order, 5000000.0, 'Catatan.', $this->staffUser(UserRole::OWNER_EXECUTIVE));

        $this->service()->approveQuote(
            $order,
            $this->clientUser($client),
            dueDate: '2026-12-01',
            workScheme: WorkScheme::FULL_PACKAGE,
            notes: 'Harap aksesoris menggunakan resleting YKK metal.',
        );

        $order->refresh();
        $this->assertSame(OrderStatus::QUOTE_APPROVED, $order->status);
        $this->assertSame('2026-12-01', $order->due_date->format('Y-m-d'));
        $this->assertSame(WorkScheme::FULL_PACKAGE, $order->work_scheme);
        $this->assertSame('Harap aksesoris menggunakan resleting YKK metal.', $order->notes);
    }

    public function test_view_portal_order_renders_back_button_and_dijawab_label_and_approve_button(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder($client, ['orders/designs/shirt.png']);
        $this->service()->provideQuote($order, 2500000.0, 'Penawaran awal.', $this->staffUser(UserRole::OWNER_EXECUTIVE));

        Storage::disk($this->getTestDisk())->put('orders/designs/shirt.png', 'content');

        $this->actingAs($this->clientUser($client));

        $response = $this->get(ViewPortalOrder::getUrl(['record' => $order], panel: 'portal', tenant: $client));
        $response->assertOk();
        $response->assertSee('Dijawab');
        $response->assertSee('Kembali');
        $response->assertSee('Setuju');
        $response->assertSee('Download Dokumen Desain');
    }

    public function test_client_can_download_their_own_order_design_file(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder($client, ['orders/designs/kaos.png']);

        Storage::disk($this->getTestDisk())->put('orders/designs/kaos.png', 'dummy image content');

        $this->actingAs($this->clientUser($client));

        $response = $this->get(route('orders.designs.download', $order));
        $response->assertOk();
        $this->assertSame('dummy image content', $response->streamedContent());
    }

    public function test_client_cannot_download_another_clients_order_design_file(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        $orderA = $this->requestedOrder($clientA, ['orders/designs/secret.png']);

        Storage::disk($this->getTestDisk())->put('orders/designs/secret.png', 'secret content');

        $this->actingAs($this->clientUser($clientB));

        $response = $this->get(route('orders.designs.download', $orderA));
        $response->assertForbidden();
    }

    public function test_admin_can_download_any_order_design_file(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder($client, ['orders/designs/sample.png']);

        Storage::disk($this->getTestDisk())->put('orders/designs/sample.png', 'sample file content');

        $this->actingAs($this->staffUser(UserRole::OWNER_EXECUTIVE));

        $response = $this->get(route('orders.designs.download', $order));
        $response->assertOk();
        $this->assertSame('sample file content', $response->streamedContent());
    }

    public function test_multiple_design_files_download_as_zip_archive(): void
    {
        $client = Client::factory()->create();
        $order = $this->requestedOrder($client, [
            'orders/designs/front.png',
            'orders/designs/back.png',
        ]);

        Storage::disk($this->getTestDisk())->put('orders/designs/front.png', 'front content');
        Storage::disk($this->getTestDisk())->put('orders/designs/back.png', 'back content');

        $this->actingAs($this->clientUser($client));

        $response = $this->get(route('orders.designs.download', $order));
        $response->assertOk();
        $this->assertStringContainsString('.zip', (string) $response->headers->get('content-disposition'));
    }

    private function getTestDisk(): string
    {
        return config('filesystems.default', 'local');
    }

    private function clientUser(Client $client): User
    {
        $user = User::factory()->create(['client_id' => $client->getKey()]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        return $user;
    }

    private function staffUser(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
