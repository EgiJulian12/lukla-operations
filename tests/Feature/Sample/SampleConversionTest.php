<?php

namespace Tests\Feature\Sample;

use App\Enums\MaterialResponsibility;
use App\Enums\OrderStatus;
use App\Enums\SampleStatus;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Exceptions\InvalidSampleState;
use App\Models\Article;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\Sample;
use App\Models\User;
use App\Services\Inventory\OrderMaterialFlowService;
use App\Services\Sample\SampleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Skenario penerimaan #4 — alur revisi sample ke order massal (FR-001..004).
 */
class SampleConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_an_approved_revision_converts_to_a_semi_cmt_mass_order(): void
    {
        $service = app(SampleService::class);
        $article = Article::factory()->create();

        $sample = $service->createSample($article, 'SMP-9001', 'Kaos Raglan');
        $v1 = $service->addRevision($sample, ['bahan' => 'Katun'], ['kain' => 1.2]);
        $v2 = $service->addRevision($sample, ['bahan' => 'Katun Combed'], ['kain' => 1.35]);

        $this->assertSame(1, $v1->version);
        $this->assertSame(2, $v2->version);

        $owner = User::factory()->create();
        $owner->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $service->submitForReview($v2);
        $service->approveRevision($v2, $owner);

        $sample->refresh();
        $this->assertSame(SampleStatus::APPROVED, $sample->status);
        $this->assertSame($v2->getKey(), $sample->approved_revision_id);

        $order = $service->convertToOrder($sample, WorkScheme::SEMI_CMT, 240);

        $this->assertSame(WorkScheme::SEMI_CMT, $order->work_scheme);
        $this->assertSame($v2->getKey(), $order->sample_revision_id);
        $this->assertSame($sample->client_id, $order->client_id);
        $this->assertSame(OrderStatus::CONFIRMED, $order->status);
        $this->assertStringContainsString('v2', $order->order_number);
    }

    public function test_a_revision_cannot_be_approved_before_it_is_submitted_for_review(): void
    {
        $service = app(SampleService::class);
        $sample = $service->createSample(Article::factory()->create(), 'SMP-9002', 'Kemeja');
        $revision = $service->addRevision($sample);

        $this->expectException(InvalidSampleState::class);

        $service->approveRevision($revision);
    }

    public function test_converting_a_sample_without_an_approved_revision_is_rejected(): void
    {
        $sample = Sample::factory()->create();

        $this->expectException(InvalidSampleState::class);

        app(SampleService::class)->convertToOrder($sample, WorkScheme::CMT, 100);
    }

    public function test_converting_twice_yields_distinct_order_numbers(): void
    {
        $service = app(SampleService::class);
        $sample = $service->createSample(Article::factory()->create(), 'SMP-9003', 'Jaket');
        $revision = $service->addRevision($sample);
        $service->submitForReview($revision);
        $service->approveRevision($revision);
        $sample->refresh();

        $first = $service->convertToOrder($sample, WorkScheme::CMT, 100);
        $second = $service->convertToOrder($sample, WorkScheme::CMT, 100);

        $this->assertNotSame($first->order_number, $second->order_number);
    }

    public function test_revision_time_study_is_recorded_against_the_revision(): void
    {
        $service = app(SampleService::class);
        $sample = $service->createSample(Article::factory()->create(), 'SMP-9004', 'Polo');
        $revision = $service->addRevision($sample);

        $study = $service->recordTimeStudy($revision, $revision->sample->article->components()->create([
            'code' => 'CMP-9004',
            'name' => 'Kerah',
            'sequence' => 1,
        ]), 95);

        $this->assertSame(95, $study->duration_seconds);
        $this->assertSame($revision->getKey(), $study->sample_revision_id);
        $this->assertSame($revision->client_id, $study->client_id);
    }

    public function test_converting_approved_sample_automatically_syncs_materials_and_requirements_to_warehouse(): void
    {
        $sampleService = app(SampleService::class);
        $flowService = app(OrderMaterialFlowService::class);
        $article = Article::factory()->create();

        $sample = $sampleService->createSample($article, 'SMP-MAT-01', 'Kemeja Flannel Pria');
        $revision = $sampleService->addRevision(
            sample: $sample,
            specs: ['warna' => 'Navy Plaid'],
            materialUsage: [
                'Kain Katun Flannel' => '1.5 meter',
                'Kancing Batok 18L' => '6 pcs',
            ],
            notes: 'Spesifikasi bahan final sample'
        );

        $owner = User::factory()->create();
        $owner->assignRole(UserRole::OWNER_EXECUTIVE->value);

        $sampleService->submitForReview($revision);
        $sampleService->approveRevision($revision, $owner);
        $sample->refresh();

        // 1. Konversi ke order massal 200 pcs dengan skema Full Package
        $order = $sampleService->convertToOrder($sample, WorkScheme::FULL_PACKAGE, 200);

        // 2. Verifikasi bahan otomatis terdaftar di master data bahan gudang (tabel materials)
        $fabricMaterial = Material::where('name', 'Kain Katun Flannel')->first();
        $this->assertNotNull($fabricMaterial);
        $this->assertSame('fabric', $fabricMaterial->category);
        $this->assertSame('meter', $fabricMaterial->uom);

        $accMaterial = Material::where('name', 'Kancing Batok 18L')->first();
        $this->assertNotNull($accMaterial);
        $this->assertSame('accessory', $accMaterial->category);
        $this->assertSame('pcs', $accMaterial->uom);

        // 3. Verifikasi data kebutuhan bahan di alur gudang (order_material_requirements)
        $requirements = $order->materialRequirements()->get();
        $this->assertCount(2, $requirements);

        $fabricReq = $requirements->firstWhere('material_id', $fabricMaterial->id);
        $this->assertNotNull($fabricReq);
        $this->assertEquals(300.0, (float) $fabricReq->required_quantity); // 1.5 * 200
        $this->assertSame('meter', $fabricReq->uom);
        $this->assertSame(MaterialResponsibility::COMPANY, $fabricReq->responsibility);

        $accReq = $requirements->firstWhere('material_id', $accMaterial->id);
        $this->assertNotNull($accReq);
        $this->assertEquals(1200.0, (float) $accReq->required_quantity); // 6 * 200
        $this->assertSame('pcs', $accReq->uom);
        $this->assertSame(MaterialResponsibility::COMPANY, $accReq->responsibility);

        // 4. Verifikasi gudang mengajukan PO untuk kebutuhan bahan order tersebut
        $flowService->triggerPoRequest($order, 'PT Tekstil Sentosa');
        $po = PurchaseOrder::where('order_id', $order->id)->first();
        $this->assertNotNull($po);
        $this->assertCount(2, $po->items);

        $poFabricItem = $po->items->firstWhere('material_id', $fabricMaterial->id);
        $this->assertNotNull($poFabricItem);
        $this->assertEquals(300.0, (float) $poFabricItem->quantity);

        $poAccItem = $po->items->firstWhere('material_id', $accMaterial->id);
        $this->assertNotNull($poAccItem);
        $this->assertEquals(1200.0, (float) $poAccItem->quantity);

        // 5. Verifikasi syncRequirementsForOrder saat jumlah order diubah (misal jadi 300 pcs)
        $order->update(['quantity' => 300]);
        $flowService->syncRequirementsForOrder($order);

        $refreshedRequirements = $order->fresh()->materialRequirements()->get();
        $refreshedFabricReq = $refreshedRequirements->firstWhere('material_id', $fabricMaterial->id);
        $this->assertEquals(450.0, (float) $refreshedFabricReq->required_quantity); // 1.5 * 300
    }
}
