<?php

namespace Tests\Feature\Sample;

use App\Enums\OrderStatus;
use App\Enums\SampleStatus;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Exceptions\InvalidSampleState;
use App\Models\Article;
use App\Models\Client;
use App\Models\Component;
use App\Models\Order;
use App\Models\Sample;
use App\Models\User;
use App\Services\Order\OrderQuotationService;
use App\Services\Sample\SampleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian Alur Terpadu Versioning Sample (v1 -> v2) & Konversi ke Order Produksi (SRS v2.0).
 * Menguji kepatuhan terhadap FR-001..004, FR-3.1, FR-4.1, UI-02, dan Skenario Uji 04.
 */
class SampleVersioningFlowTest extends TestCase
{
    use RefreshDatabase;

    private SampleService $sampleService;

    private OrderQuotationService $quotationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->sampleService = app(SampleService::class);
        $this->quotationService = app(OrderQuotationService::class);
    }

    public function test_order_sample_request_generates_one_to_one_article_and_sample_v1(): void
    {
        $client = Client::factory()->create(['name' => 'Brand Lokal Bandung']);
        $owner = $this->createOwner();

        // 1. Klien submit order request di portal
        $order = $this->quotationService->submitRequest(
            client: $client,
            designPaths: ['designs/hoodie-front.png', 'designs/hoodie-back.png'],
            description: 'Hoodie Oversize Katun Fleece Gramasi 330',
        );

        $this->assertSame(OrderStatus::REQUESTED, $order->status);

        // 2. Owner memberikan penawaran
        $this->quotationService->provideQuote($order, 125000, 'Harga CMT per pcs untuk min order 200 pcs', $owner);
        $this->assertSame(OrderStatus::QUOTED, $order->status);

        // 3. Klien setujui penawaran (QUOTE_APPROVED)
        $this->quotationService->approveQuote($order);
        $this->assertSame(OrderStatus::QUOTE_APPROVED, $order->status);

        // 4. Owner kirim permintaan sample ke Tim Sample
        $this->quotationService->requestSample($order, $owner);
        $order->refresh();
        $this->assertNotNull($order->sample_requested_at);
        $this->assertNull($order->sample_id);

        // 5. Tim Sample menentukan artikel (baru) dan memulai sample v1
        $sample = $this->sampleService->initializeSampleFromOrder($order, [
            'article_mode' => 'new',
            'name' => 'Hoodie Oversize Katun Fleece',
            'code' => $order->order_number,
            'description' => $order->description,
        ]);

        $order->refresh();
        $this->assertNotNull($order->article_id);
        $this->assertSame($sample->article_id, $order->article_id);
        $this->assertSame($order->getKey(), $order->article->order_id);
        $this->assertSame($sample->getKey(), $order->sample_id);

        // Sample otomatis memiliki revisi pertama (v1) dalam status DRAFT
        $this->assertSame(SampleStatus::DRAFT, $sample->status);
        $this->assertCount(1, $sample->revisions);

        $v1 = $sample->currentRevision;
        $this->assertNotNull($v1);
        $this->assertSame(1, $v1->version);
        $this->assertSame('v1', $v1->version_label);
        $this->assertSame(SampleStatus::DRAFT, $v1->status);
        $this->assertTrue($v1->isEditable());
    }

    public function test_sample_v1_records_components_and_time_study_then_submits_for_review(): void
    {
        $article = Article::factory()->create();
        $sample = $this->sampleService->createSample($article, 'SMP-TEST-01', 'Kemeja Flannel');
        $v1 = $this->sampleService->addRevision(
            sample: $sample,
            specs: ['bahan' => 'Flannel Wool', 'kancing' => 'Kayu 18L'],
            materialUsage: ['kain_utama' => '1.6 meter', 'benang' => '1 spool'],
            notes: 'Sample awal cutting pola standar',
            resultImagePath: 'samples/flannel-v1.jpg',
        );

        // Tim sample mendefinisikan 3 komponen pada artikel
        $compKerah = Component::create(['article_id' => $article->getKey(), 'code' => 'K-01', 'name' => 'Kerah', 'sequence' => 1]);
        $compLengan = Component::create(['article_id' => $article->getKey(), 'code' => 'L-01', 'name' => 'Lengan', 'sequence' => 2]);
        $compBadan = Component::create(['article_id' => $article->getKey(), 'code' => 'B-01', 'name' => 'Badan & Kancing', 'sequence' => 3]);

        // Stopwatch digital merekam durasi detik per komponen
        $study1 = $this->sampleService->recordTimeStudy($v1, $compKerah, 90, 'Stopwatch: 1m 30s');
        $study2 = $this->sampleService->recordTimeStudy($v1, $compLengan, 120, 'Stopwatch: 2m 00s');
        $study3 = $this->sampleService->recordTimeStudy($v1, $compBadan, 240, 'Stopwatch: 4m 00s');

        $this->assertSame(450, $v1->totalTimeStudySeconds());
        $this->assertSame('samples/flannel-v1.jpg', $v1->result_image_path);

        // Submit untuk review
        $submitted = $this->sampleService->submitForReview($v1);
        $this->assertSame(SampleStatus::IN_REVIEW, $submitted->status);
        $this->assertFalse($submitted->isEditable());
        $this->assertTrue($submitted->canBeRevised());
    }

    public function test_scenario_04_srs_cloning_v1_to_v2_maintains_data_isolation(): void
    {
        $article = Article::factory()->create();
        $sample = $this->sampleService->createSample($article, 'SMP-SCENARIO-04', 'Jaket Bomber');

        // Buat v1 dengan komponen & time study awal
        $v1 = $this->sampleService->addRevision(
            sample: $sample,
            specs: ['zipper' => 'YKK Besi No 5', 'furing' => 'Despo'],
            materialUsage: ['kain_luar' => '1.7 meter'],
            notes: 'Versi pertama v1',
            resultImagePath: 'samples/bomber-v1.jpg',
        );

        $compBadan = Component::create(['article_id' => $article->getKey(), 'code' => 'BDN', 'name' => 'Badan Depan-Belakang', 'sequence' => 1]);
        $compRib = Component::create(['article_id' => $article->getKey(), 'code' => 'RIB', 'name' => 'Rib Leher & Manset', 'sequence' => 2]);

        $this->sampleService->recordTimeStudy($v1, $compBadan, 180, 'Time study awal badan');
        $this->sampleService->recordTimeStudy($v1, $compRib, 90, 'Time study awal rib');

        // v1 diajukan untuk review
        $this->sampleService->submitForReview($v1);
        $this->assertSame(SampleStatus::IN_REVIEW, $v1->status);

        // Klien / Owner meminta revisi (Opsi A) -> Sistem melakukan cloning ke v2
        $v2 = $this->sampleService->createRevisionFromPrevious(
            previous: $v1,
            overrides: [
                'specs' => ['zipper' => 'YKK Waterproof No 5', 'furing' => 'Dormeuil'],
                'result_image_path' => 'samples/bomber-v2.jpg',
            ],
            notes: 'Revisi v2: Ganti furing lebih lembut dan zipper waterproof',
        );

        // Verifikasi status v1 dan v2
        $v1->refresh();
        $this->assertSame(SampleStatus::REVISED, $v1->status);
        $this->assertFalse($v1->isEditable());

        $this->assertSame(2, $v2->version);
        $this->assertSame('v2', $v2->version_label);
        $this->assertSame($v1->getKey(), $v2->parent_revision_id);
        $this->assertSame(SampleStatus::DRAFT, $v2->status);
        $this->assertTrue($v2->isEditable());

        // Verifikasi seluruh time study dari v1 berhasil di-clone ke v2
        $this->assertCount(2, $v2->timeStudies);
        $this->assertSame(270, $v2->totalTimeStudySeconds());

        // Tim Sample memodifikasi time study di v2 (misal proses jahit rib disesuaikan jadi 135 detik)
        $this->sampleService->recordTimeStudy($v2, $compRib, 135, 'Penyesuaian teknik sambungan manset rib');

        // VERIFIKASI KRITIS SKENARIO UJI 04 SRS:
        // 1. Data time study pada v1 HARUS TETAP 90 detik (tidak berubah)
        $v1RibStudy = $v1->timeStudies()->where('component_id', $compRib->getKey())->first();
        $this->assertNotNull($v1RibStudy);
        $this->assertSame(90, $v1RibStudy->duration_seconds);
        $this->assertSame('Time study awal rib', $v1RibStudy->notes);
        $this->assertSame('YKK Besi No 5', $v1->specs['zipper']);

        // 2. Data time study pada v2 TELAH BERUBAH menjadi 135 detik
        $v2RibStudy = $v2->timeStudies()->where('component_id', $compRib->getKey())->first();
        $this->assertNotNull($v2RibStudy);
        $this->assertSame(135, $v2RibStudy->duration_seconds);
        $this->assertSame('Penyesuaian teknik sambungan manset rib', $v2RibStudy->notes);
        $this->assertSame('YKK Waterproof No 5', $v2->specs['zipper']);
        $this->assertSame(315, $v2->totalTimeStudySeconds());
    }

    public function test_approved_sample_v2_converts_to_mass_order_locking_specs_and_scheme(): void
    {
        $article = Article::factory()->create(['name' => 'Kemeja Tactical']);
        $sample = $this->sampleService->createSample($article, 'SMP-TACTICAL', 'Kemeja Tactical 5.11');

        $v1 = $this->sampleService->addRevision($sample);
        $this->sampleService->submitForReview($v1);

        // Buat v2 dan ajukan
        $v2 = $this->sampleService->createRevisionFromPrevious($v1, notes: 'Perbaikan saku bobok');
        $this->sampleService->submitForReview($v2);

        // Owner menyetujui v2
        $owner = $this->createOwner();
        $this->sampleService->approveRevision($v2, $owner);

        $sample->refresh();
        $this->assertSame(SampleStatus::APPROVED, $sample->status);
        $this->assertSame($v2->getKey(), $sample->approved_revision_id);
        $this->assertTrue($sample->isReadyForMassProduction());

        // Konversi ke Order Produksi Massal (Gatekeeping FR-4.1)
        // Pengguna memilih skema maklon SEMI_CMT dan kuantitas 500 pcs
        $massOrder = $this->sampleService->convertToOrder(
            sample: $sample,
            scheme: WorkScheme::SEMI_CMT,
            quantity: 500,
            dueDate: now()->addWeeks(3),
            notes: 'Produksi massal batch 1 500 pcs',
        );

        $this->assertSame($article->getKey(), $massOrder->article_id);
        $this->assertSame($sample->getKey(), $massOrder->sample_id);
        $this->assertSame($v2->getKey(), $massOrder->sample_revision_id);
        $this->assertSame(WorkScheme::SEMI_CMT, $massOrder->work_scheme);
        $this->assertSame(500, $massOrder->quantity);
        $this->assertSame(OrderStatus::CONFIRMED, $massOrder->status);
        $this->assertStringContainsString('v2', $massOrder->order_number);

        // Order siap diteruskan ke lantai produksi
        $massOrder->update(['status' => OrderStatus::READY_FOR_PRODUCTION]);
        $this->assertSame(OrderStatus::READY_FOR_PRODUCTION, $massOrder->refresh()->status);
    }

    public function test_initialize_sample_from_order_sets_up_article_and_v1_draft(): void
    {
        $client = Client::factory()->create(['name' => 'PT Tekstil Jaya']);
        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'status' => OrderStatus::QUOTE_APPROVED,
            'description' => 'Kaos Polo Katun Combed 30s',
            'order_number' => 'ORD-TEST-POLO',
        ]);

        $sample = $this->sampleService->initializeSampleFromOrder($order, [
            'name' => 'Polo Shirt Custom',
            'code' => 'ART-POLO-001',
            'description' => 'Polo katun bordir dada kiri',
        ]);

        $order->refresh();
        $this->assertNotNull($order->article_id);
        $this->assertSame($order->article_id, $sample->article_id);
        $this->assertSame($order->getKey(), $sample->article->order_id);
        $this->assertSame($sample->getKey(), $order->sample_id);
        $this->assertSame('Polo Shirt Custom', $sample->article->name);
        $this->assertSame('ART-POLO-001', $sample->article->code);

        $v1 = $sample->currentRevision;
        $this->assertNotNull($v1);
        $this->assertSame(1, $v1->version);
        $this->assertSame(SampleStatus::DRAFT, $v1->status);
        $this->assertSame('ORD-TEST-POLO', $v1->specs['order_number']);
        $this->assertTrue($v1->isEditable());
    }

    public function test_sync_components_and_time_studies_updates_and_isolates_records(): void
    {
        $article = Article::factory()->create(['name' => 'Jaket Parka']);
        $sample = $this->sampleService->createSample($article, 'SMP-PARKA', 'Jaket Parka');
        $v1 = $this->sampleService->addRevision($sample);

        // 1. Sinkronisasi 3 komponen pertama
        $items = [
            ['component_name' => 'Kerah Standar', 'duration_seconds' => 75, 'notes' => 'Jahit kerah'],
            ['component_name' => 'Lengan Kiri & Kanan', 'duration_seconds' => 120, 'notes' => 'Sambung manset'],
            ['component_name' => 'Saku Depan', 'duration_seconds' => 60, 'notes' => 'Pasang saku tempel'],
        ];

        $this->sampleService->syncComponentsAndTimeStudies($v1, $items);

        $v1->refresh();
        $this->assertCount(3, $v1->timeStudies);
        $this->assertSame(255, $v1->totalTimeStudySeconds());
        $this->assertDatabaseHas('components', ['article_id' => $article->getKey(), 'name' => 'Kerah Standar']);
        $this->assertDatabaseHas('components', ['article_id' => $article->getKey(), 'name' => 'Saku Depan']);

        // 2. Perbarui: ubah durasi Kerah, hilangkan Saku Depan, tambah Tali Hoodie
        $updatedItems = [
            ['component_name' => 'Kerah Standar', 'duration_seconds' => 90, 'notes' => 'Penyesuaian obras'],
            ['component_name' => 'Lengan Kiri & Kanan', 'duration_seconds' => 120, 'notes' => 'Sambung manset'],
            ['component_name' => 'Tali Hoodie', 'duration_seconds' => 30, 'notes' => 'Pasang stopper'],
        ];

        $this->sampleService->syncComponentsAndTimeStudies($v1, $updatedItems);

        $v1->refresh();
        $this->assertCount(3, $v1->timeStudies);
        $this->assertSame(240, $v1->totalTimeStudySeconds());

        // Pastikan Saku Depan tidak lagi tercatat di time study v1
        $this->assertFalse(
            $v1->timeStudies()->whereHas('component', fn ($q) => $q->where('name', 'Saku Depan'))->exists()
        );

        // Pastikan Tali Hoodie tercatat
        $this->assertTrue(
            $v1->timeStudies()->whereHas('component', fn ($q) => $q->where('name', 'Tali Hoodie'))->exists()
        );

        // 3. Jika status sudah di-submit (IN_REVIEW), sync harus gagal (throw InvalidSampleState)
        $this->sampleService->submitForReview($v1);
        $this->expectException(InvalidSampleState::class);

        $this->sampleService->syncComponentsAndTimeStudies($v1, [
            ['component_name' => 'Kerah Standar', 'duration_seconds' => 100],
        ]);
    }

    public function test_existing_order_is_updated_without_duplicate_when_converting_approved_sample(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create([
            'client_id' => $client->getKey(),
            'status' => OrderStatus::QUOTE_APPROVED,
            'quantity' => 50,
            'work_scheme' => WorkScheme::CMT,
        ]);

        $sample = $this->sampleService->initializeSampleFromOrder($order, [
            'name' => 'Celana Chino',
            'code' => 'ART-CHINO-01',
        ]);

        $v1 = $sample->currentRevision;
        $this->sampleService->syncComponentsAndTimeStudies($v1, [
            ['component_name' => 'Pola Depan', 'duration_seconds' => 100],
        ]);
        $this->sampleService->submitForReview($v1);

        $owner = $this->createOwner();
        $this->sampleService->approveRevision($v1, $owner);
        $sample->refresh();

        $initialOrderCount = Order::count();

        // Simulasikan action ConvertToOrderAction ketika order sudah terikat (article->order)
        $existingOrder = $sample->article?->order;
        $this->assertNotNull($existingOrder);
        $this->assertSame($order->getKey(), $existingOrder->getKey());

        $existingOrder->update([
            'sample_id' => $sample->getKey(),
            'sample_revision_id' => $sample->approved_revision_id,
            'work_scheme' => WorkScheme::FULL_PACKAGE,
            'quantity' => 1000,
            'status' => OrderStatus::READY_FOR_PRODUCTION,
        ]);

        // Verifikasi tidak ada order duplikat
        $this->assertSame($initialOrderCount, Order::count());

        $order->refresh();
        $this->assertSame(OrderStatus::READY_FOR_PRODUCTION, $order->status);
        $this->assertSame(WorkScheme::FULL_PACKAGE, $order->work_scheme);
        $this->assertSame(1000, $order->quantity);
        $this->assertSame($v1->getKey(), $order->sample_revision_id);
    }

    public function test_sample_team_cannot_approve_or_reject_sample_revision(): void
    {
        $article = Article::factory()->create();
        $sample = $this->sampleService->createSample($article, 'SMP-TEST-RBAC', 'Kaos Polo');
        $v1 = $this->sampleService->addRevision($sample);
        $this->sampleService->submitForReview($v1);

        $sampleTeam = User::factory()->create(['name' => 'Tim Sample User']);
        $sampleTeam->assignRole(UserRole::SAMPLE_TEAM->value);

        $this->assertFalse($sampleTeam->can('approve', $sample));
        $this->assertFalse($sampleTeam->can('reject', $sample));
        $this->assertFalse($sampleTeam->can('approve', $v1));
        $this->assertFalse($sampleTeam->can('reject', $v1));
    }

    public function test_owner_cannot_approve_sample_revision_but_can_convert_to_mass_order(): void
    {
        $article = Article::factory()->create();
        $sample = $this->sampleService->createSample($article, 'SMP-TEST-OWNER', 'Kaos Polo');
        $v1 = $this->sampleService->addRevision($sample);
        $this->sampleService->submitForReview($v1);

        $owner = $this->createOwner();

        // Persetujuan (ACC) adalah wewenang Klien, bukan Owner
        $this->assertFalse($owner->can('approve', $sample));
        $this->assertFalse($owner->can('reject', $sample));
        $this->assertFalse($owner->can('approve', $v1));
        $this->assertFalse($owner->can('reject', $v1));

        // Setelah disahkan, HANYA Owner yang berhak mengonversi ke order massal
        $v1->update(['status' => SampleStatus::APPROVED]);
        $sample->update(['status' => SampleStatus::APPROVED, 'approved_revision_id' => $v1->getKey()]);

        $this->assertTrue($owner->can('convertToOrder', $sample));

        $sampleTeam = User::factory()->create(['name' => 'Tim Sample']);
        $sampleTeam->assignRole(UserRole::SAMPLE_TEAM->value);
        $this->assertFalse($sampleTeam->can('convertToOrder', $sample));
    }

    public function test_client_can_approve_own_sample_revision(): void
    {
        $client = Client::factory()->create();
        $clientUser = User::factory()->create(['client_id' => $client->getKey(), 'name' => 'Klien User']);
        $clientUser->assignRole(UserRole::CLIENT_MAKLON->value);

        $article = Article::factory()->create(['client_id' => $client->getKey()]);
        $sample = $this->sampleService->createSample($article, 'SMP-TEST-CLIENT', 'Kemeja Katun');
        $v1 = $this->sampleService->addRevision($sample);
        $this->sampleService->submitForReview($v1);

        $this->assertTrue($clientUser->can('approve', $sample));
        $this->assertTrue($clientUser->can('reject', $sample));
        $this->assertTrue($clientUser->can('approve', $v1));
        $this->assertTrue($clientUser->can('reject', $v1));
    }

    public function test_different_client_cannot_approve_other_client_sample(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        $clientUserB = User::factory()->create(['client_id' => $clientB->getKey(), 'name' => 'Klien B User']);
        $clientUserB->assignRole(UserRole::CLIENT_MAKLON->value);

        $articleA = Article::factory()->create(['client_id' => $clientA->getKey()]);
        $sampleA = $this->sampleService->createSample($articleA, 'SMP-TEST-A', 'Kemeja A');
        $v1 = $this->sampleService->addRevision($sampleA);
        $this->sampleService->submitForReview($v1);

        $this->assertFalse($clientUserB->can('approve', $sampleA));
        $this->assertFalse($clientUserB->can('reject', $sampleA));
        $this->assertFalse($clientUserB->can('approve', $v1));
        $this->assertFalse($clientUserB->can('reject', $v1));
    }

    private function createOwner(): User
    {
        $user = User::factory()->create(['name' => 'Owner Eksekutif']);
        $user->assignRole(UserRole::OWNER_EXECUTIVE->value);

        return $user;
    }
}
