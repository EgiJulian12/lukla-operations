<?php

namespace Tests\Feature\Quality;

use App\Enums\QcResult;
use App\Enums\RepairStatus;
use App\Exceptions\InvalidQcInspection;
use App\Models\Assembly;
use App\Models\DefectCategory;
use App\Models\QcRecord;
use App\Models\Repair;
use App\Models\User;
use App\Models\WasteRecord;
use App\Services\Quality\QcService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian alur inspeksi QC komprehensif (Modul 6, FR-008, FR-014, BR 4.6).
 * Memverifikasi:
 * 1. PASS: lolos ke Finished Goods, eligible untuk QC bonus counter (+1 unit), tanpa defect category.
 * 2. REPAIR: wajib defect category, otomatis buat tiket repair via RepairManager, status PENDING, tidak masuk bonus pool.
 * 3. SCRAP: wajib defect category, otomatis buat waste_records teratribusi client/order, tidak masuk bonus pool.
 */
class QcInspectionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_qc_pass_is_eligible_for_bonus_pool_and_records_correctly(): void
    {
        $inspector = User::factory()->create();
        $assembly = Assembly::factory()->create();

        $service = app(QcService::class);
        $record = $service->recordInspection(
            assembly: $assembly,
            result: QcResult::PASS,
            quantity: 15,
            defectCategory: null,
            notes: 'Jahitan dan finishing rapi',
            inspector: $inspector,
        );

        $this->assertInstanceOf(QcRecord::class, $record);
        $this->assertSame(QcResult::PASS, $record->result);
        $this->assertSame(15, $record->quantity);
        $this->assertTrue($record->countsTowardBonusPool());
        $this->assertSame($assembly->client_id, $record->client_id);
        $this->assertSame($assembly->order_id, $record->order_id);
        $this->assertSame($inspector->getKey(), $record->inspected_by);

        // Tidak ada tiket repair atau waste
        $this->assertDatabaseMissing('repairs', ['qc_record_id' => $record->id]);
        $this->assertDatabaseMissing('waste_records', ['order_id' => $assembly->order_id]);
    }

    public function test_qc_repair_requires_defect_category(): void
    {
        $assembly = Assembly::factory()->create();
        $service = app(QcService::class);

        $this->expectException(InvalidQcInspection::class);

        $service->recordInspection(
            assembly: $assembly,
            result: QcResult::REPAIR,
            quantity: 2,
            defectCategory: null,
        );
    }

    public function test_qc_repair_creates_repair_ticket_and_does_not_count_toward_bonus_pool(): void
    {
        $inspector = User::factory()->create();
        $assembly = Assembly::factory()->create();
        $defectCategory = DefectCategory::factory()->create(['name' => 'Jahitan Melenceng']);

        $service = app(QcService::class);
        $record = $service->recordInspection(
            assembly: $assembly,
            result: QcResult::REPAIR,
            quantity: 3,
            defectCategory: $defectCategory,
            notes: 'Perlu jahit ulang saku',
            inspector: $inspector,
        );

        $this->assertSame(QcResult::REPAIR, $record->result);
        $this->assertFalse($record->countsTowardBonusPool());

        // Verifikasi tiket repair otomatis dibuat
        $this->assertDatabaseHas('repairs', [
            'qc_record_id' => $record->id,
            'status' => RepairStatus::PENDING->value,
            'notes' => 'Perlu jahit ulang saku',
        ]);

        $repair = Repair::where('qc_record_id', $record->id)->first();
        $this->assertNotNull($repair);
        $this->assertSame(RepairStatus::PENDING, $repair->status);
        $this->assertNull($repair->completed_at);
    }

    public function test_qc_scrap_requires_defect_category(): void
    {
        $assembly = Assembly::factory()->create();
        $service = app(QcService::class);

        $this->expectException(InvalidQcInspection::class);

        $service->recordInspection(
            assembly: $assembly,
            result: QcResult::SCRAP,
            quantity: 1,
            defectCategory: null,
        );
    }

    public function test_qc_scrap_creates_waste_record_with_client_and_order_attribution(): void
    {
        $inspector = User::factory()->create();
        $assembly = Assembly::factory()->create();
        $defectCategory = DefectCategory::factory()->create(['name' => 'Kain Robek / Rusak Parah']);

        $service = app(QcService::class);
        $record = $service->recordInspection(
            assembly: $assembly,
            result: QcResult::SCRAP,
            quantity: 4,
            defectCategory: $defectCategory,
            notes: 'Bahan robek di area krusial, tidak bisa diperbaiki',
            inspector: $inspector,
        );

        $this->assertSame(QcResult::SCRAP, $record->result);
        $this->assertFalse($record->countsTowardBonusPool());

        // Verifikasi otomatis tercatat ke waste_records
        $this->assertDatabaseHas('waste_records', [
            'client_id' => $assembly->client_id,
            'order_id' => $assembly->order_id,
            'quantity' => 4.00,
            'uom' => 'pcs',
            'category' => 'Kain Robek / Rusak Parah',
            'recorded_by' => $inspector->getKey(),
        ]);

        $waste = WasteRecord::where('order_id', $assembly->order_id)->first();
        $this->assertNotNull($waste);
        $this->assertEquals(4.0, (float) $waste->quantity);
        $this->assertSame($assembly->client_id, $waste->client_id);
    }
}
