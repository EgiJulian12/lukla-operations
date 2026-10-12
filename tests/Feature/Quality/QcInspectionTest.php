<?php

namespace Tests\Feature\Quality;

use App\Enums\QcResult;
use App\Exceptions\InvalidQcInspection;
use App\Models\Assembly;
use App\Models\DefectCategory;
use App\Services\Quality\QcService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inspeksi QC: lolos, repair, dan scrap (FR-014).
 */
class QcInspectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_a_passed_inspection_needs_no_defect_category(): void
    {
        $assembly = Assembly::factory()->create();

        $record = app(QcService::class)->recordInspection($assembly, QcResult::PASS, 10);

        $this->assertSame(QcResult::PASS, $record->result);
        $this->assertNull($record->defect_category_id);
        $this->assertSame($assembly->client_id, $record->client_id);
        $this->assertSame($assembly->order_id, $record->order_id);
        $this->assertTrue($record->countsTowardBonusPool());
    }

    public function test_scrap_without_a_defect_category_is_rejected(): void
    {
        $assembly = Assembly::factory()->create();

        $this->expectException(InvalidQcInspection::class);

        app(QcService::class)->recordInspection($assembly, QcResult::SCRAP, 5);
    }

    public function test_scrap_with_a_defect_category_is_recorded_but_excluded_from_the_bonus_pool(): void
    {
        $assembly = Assembly::factory()->create();
        $category = DefectCategory::factory()->create();

        $record = app(QcService::class)->recordInspection($assembly, QcResult::SCRAP, 5, $category);

        $this->assertSame($category->getKey(), $record->defect_category_id);
        $this->assertFalse($record->countsTowardBonusPool());
    }
}
