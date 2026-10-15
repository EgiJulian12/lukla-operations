<?php

namespace Tests\Unit;

use App\Services\Ai\IntentRouter;
use Tests\TestCase;

class IntentRouterTest extends TestCase
{
    private IntentRouter $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = new IntentRouter;
    }

    public function test_detects_doc_intent_for_generic_question(): void
    {
        $result = $this->router->detectIntent('Bagaimana cara membuat akun baru?');

        $this->assertSame('doc', $result['intent']);
        $this->assertNull($result['model']);
    }

    public function test_detects_db_intent_for_order_keyword_in_indonesian(): void
    {
        $result = $this->router->detectIntent('Lihat daftar pesanan saya');

        $this->assertSame('db', $result['intent']);
        $this->assertSame('orders', $result['model']);
    }

    public function test_detects_db_intent_for_order_keyword_in_english(): void
    {
        $result = $this->router->detectIntent('Show me my orders');

        $this->assertSame('db', $result['intent']);
        $this->assertSame('orders', $result['model']);
    }

    public function test_detects_db_intent_for_inventory_keyword(): void
    {
        $result = $this->router->detectIntent('Cek stok gudang saya');

        $this->assertSame('db', $result['intent']);
        $this->assertSame('inventory_items', $result['model']);
    }

    public function test_detects_db_intent_for_material_keyword(): void
    {
        $result = $this->router->detectIntent('Tampilkan daftar bahan');

        $this->assertSame('db', $result['intent']);
        $this->assertSame('materials', $result['model']);
    }

    public function test_detects_db_intent_for_output_keyword(): void
    {
        $result = $this->router->detectIntent('Berapa output penjahit minggu ini?');

        $this->assertSame('db', $result['intent']);
        $this->assertSame('output_entries', $result['model']);
    }

    public function test_detects_db_intent_for_rate_card_keyword(): void
    {
        $result = $this->router->detectIntent('Berapa tarif jahit saat ini?');

        $this->assertSame('db', $result['intent']);
        $this->assertSame('rate_cards', $result['model']);
    }

    public function test_detects_hybrid_intent_when_order_keyword_and_doc_keyword_present(): void
    {
        $result = $this->router->detectIntent('Bagaimana proses pesanan saya bisa selesai?');

        $this->assertSame('hybrid', $result['intent']);
        $this->assertSame('orders', $result['model']);
    }

    public function test_detects_hybrid_intent_in_english(): void
    {
        $result = $this->router->detectIntent('How does the inventory process work?');

        $this->assertSame('hybrid', $result['intent']);
        $this->assertSame('inventory_items', $result['model']);
    }

    public function test_extracts_order_number_filter(): void
    {
        $result = $this->router->detectIntent('Status pesanan ORD-2024-001 apa?');

        $this->assertSame('orders', $result['model']);
        $this->assertArrayHasKey('order_number', $result['filters']);
        $this->assertSame('ORD-2024-001', $result['filters']['order_number']);
    }

    public function test_extracts_status_filter(): void
    {
        $result = $this->router->detectIntent('Tampilkan order status:completed');

        $this->assertArrayHasKey('status', $result['filters']);
        $this->assertSame('completed', $result['filters']['status']);
    }

    public function test_extracts_state_filter_for_inventory(): void
    {
        $result = $this->router->detectIntent('Lihat stok state:raw');

        $this->assertArrayHasKey('state', $result['filters']);
        $this->assertSame('raw', $result['filters']['state']);
    }

    public function test_returns_empty_filters_when_no_entity_present(): void
    {
        $result = $this->router->detectIntent('Daftar pesanan saya');

        $this->assertIsArray($result['filters']);
        $this->assertEmpty($result['filters']);
    }
}
