<?php

namespace Tests\Feature\Finance;

use App\Exceptions\InvalidRateMarginException;
use App\Services\Finance\RateManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit test RateManager — Req 2.1-2.7 (computeAvailableMargin,
 * validateRateKomponen, validateTotalComponentsMargin).
 *
 * enforceSequentialApproval membutuhkan data Order + Component dengan kolom
 * approval_status yang belum ada di skema saat ini, sehingga tidak diuji di sini.
 */
class RateManagerTest extends TestCase
{
    private RateManager $rateManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rateManager = new RateManager;
    }

    // -------------------------------------------------------------------------
    // computeAvailableMargin (Req 2.1-2.3)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_computes_available_margin_correctly(): void
    {
        // Margin = 100_000 - (40_000 + 20_000 + 15_000) = 25_000
        $margin = $this->rateManager->computeAvailableMargin(100_000, 40_000, 20_000, 15_000);

        $this->assertSame(25_000.0, $margin);
    }

    #[Test]
    public function it_throws_when_margin_is_zero(): void
    {
        $this->expectException(InvalidRateMarginException::class);
        $this->expectExceptionMessageMatches('/tidak boleh nol atau negatif/i');

        // Margin = 50_000 - (20_000 + 15_000 + 15_000) = 0
        $this->rateManager->computeAvailableMargin(50_000, 20_000, 15_000, 15_000);
    }

    #[Test]
    public function it_throws_when_margin_is_negative(): void
    {
        $this->expectException(InvalidRateMarginException::class);
        $this->expectExceptionMessageMatches('/tidak boleh nol atau negatif/i');

        // Margin = 50_000 - (30_000 + 15_000 + 10_000) = -5_000
        $this->rateManager->computeAvailableMargin(50_000, 30_000, 15_000, 10_000);
    }

    #[Test]
    public function it_accepts_fractional_selling_prices(): void
    {
        // Margin = 99_999.99 - (40_000 + 20_000 + 15_000) = 24_999.99
        $margin = $this->rateManager->computeAvailableMargin(99_999.99, 40_000, 20_000, 15_000);

        $this->assertEqualsWithDelta(24_999.99, $margin, 0.001);
    }

    // -------------------------------------------------------------------------
    // validateRateKomponen (Req 2.4-2.5)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_accepts_component_rate_within_available_margin(): void
    {
        $result = $this->rateManager->validateRateKomponen(5_000, 25_000);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_accepts_component_rate_equal_to_available_margin(): void
    {
        // Batas atas: rate == margin adalah valid (tidak melebihi)
        $result = $this->rateManager->validateRateKomponen(25_000, 25_000);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_throws_when_component_rate_exceeds_available_margin(): void
    {
        $this->expectException(InvalidRateMarginException::class);
        $this->expectExceptionMessageMatches('/melebihi batas maksimum/i');

        $this->rateManager->validateRateKomponen(30_000, 25_000);
    }

    #[Test]
    public function exception_message_includes_limit_values_for_component_rate(): void
    {
        try {
            $this->rateManager->validateRateKomponen(30_000, 25_000);
            $this->fail('Expected InvalidRateMarginException was not thrown.');
        } catch (InvalidRateMarginException $e) {
            $this->assertStringContainsString('30,000.00', $e->getMessage());
            $this->assertStringContainsString('25,000.00', $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // validateTotalComponentsMargin (Req 2.6-2.7)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_accepts_total_component_rates_within_margin(): void
    {
        $result = $this->rateManager->validateTotalComponentsMargin(
            [5_000, 8_000, 7_000],
            25_000,
        );

        $this->assertTrue($result);
    }

    #[Test]
    public function it_accepts_total_component_rates_equal_to_margin(): void
    {
        // 10_000 + 10_000 + 5_000 = 25_000 == available margin
        $result = $this->rateManager->validateTotalComponentsMargin(
            [10_000, 10_000, 5_000],
            25_000,
        );

        $this->assertTrue($result);
    }

    #[Test]
    public function it_throws_when_total_component_rates_exceed_margin(): void
    {
        $this->expectException(InvalidRateMarginException::class);
        $this->expectExceptionMessageMatches('/melebihi margin tersedia/i');

        // 10_000 + 10_000 + 8_000 = 28_000 > 25_000
        $this->rateManager->validateTotalComponentsMargin(
            [10_000, 10_000, 8_000],
            25_000,
        );
    }

    #[Test]
    public function exception_message_includes_shortfall_amount(): void
    {
        // Shortfall = 28_000 - 25_000 = 3_000
        try {
            $this->rateManager->validateTotalComponentsMargin(
                [10_000, 10_000, 8_000],
                25_000,
            );
            $this->fail('Expected InvalidRateMarginException was not thrown.');
        } catch (InvalidRateMarginException $e) {
            $this->assertStringContainsString('3,000.00', $e->getMessage());
        }
    }

    #[Test]
    public function it_handles_empty_component_rates_array(): void
    {
        // Array kosong: total = 0, selalu valid
        $result = $this->rateManager->validateTotalComponentsMargin([], 25_000);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_single_component_rate_array(): void
    {
        $result = $this->rateManager->validateTotalComponentsMargin([20_000], 25_000);

        $this->assertTrue($result);
    }
}
