<?php

namespace Tests\Feature;

use App\Services\Ai\RagService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class LandingPageChatTest extends TestCase
{
    public function test_chat_modal_mounts_with_welcoming_message_in_general_mode(): void
    {
        $component = Livewire::test('chat-modal', ['mode' => 'general'])
            ->call('openChat');

        $component->assertSee('Selamat datang di')
            ->assertSee('Lukla Operations Engine')
            ->assertSee('Skema Maklon')
            ->assertSee('Alur Pembuatan Sample')
            ->assertSee('Proses Produksi Massal');
    }

    public function test_chat_modal_formats_bullet_points_into_html_list(): void
    {
        $component = Livewire::test('chat-modal', ['mode' => 'general']);

        $rawText = "Berikut adalah layanan kami:\n• Skema Maklon CMT\n• Pembuatan Sample Fisik\n• Kontrol Kualitas QC";
        $html = $component->instance()->formatMessage($rawText);

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('Skema Maklon CMT', $html);
        $this->assertStringContainsString('Pembuatan Sample Fisik', $html);
        $this->assertStringContainsString('Kontrol Kualitas QC', $html);
    }

    public function test_chat_modal_formats_numbered_lists_into_ordered_list(): void
    {
        $component = Livewire::test('chat-modal', ['mode' => 'general']);

        $rawText = "Tahapan produksi:\n1. Pembuatan Pola\n2. Proses Cutting\n3. Sewing";
        $html = $component->instance()->formatMessage($rawText);

        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('Pembuatan Pola', $html);
        $this->assertStringContainsString('Proses Cutting', $html);
        $this->assertStringContainsString('Sewing', $html);
    }

    public function test_chat_modal_formats_separate_paragraphs_with_p_tags(): void
    {
        $component = Livewire::test('chat-modal', ['mode' => 'general']);

        $rawText = "Paragraf pertama penjelasan umum.\n\nParagraf kedua penjelasan teknis lanjutan.";
        $html = $component->instance()->formatMessage($rawText);

        $this->assertStringContainsString('<p>Paragraf pertama penjelasan umum.</p>', $html);
        $this->assertStringContainsString('<p>Paragraf kedua penjelasan teknis lanjutan.</p>', $html);
    }

    public function test_chat_modal_escapes_unsafe_html_tags(): void
    {
        $component = Livewire::test('chat-modal', ['mode' => 'general']);

        $rawText = "Jawaban dari bot <script>alert('xss')</script> aman.";
        $html = $component->instance()->formatMessage($rawText);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_chat_modal_in_portal_mode_includes_client_name_in_greeting(): void
    {
        $component = Livewire::test('chat-modal', [
            'mode' => 'portal',
            'clientContext' => ['client_name' => 'Brand Merdeka'],
        ])->call('openChat');

        $component->assertSee('Brand Merdeka')
            ->assertSee('Portal Klien Lukla')
            ->assertSee('Status & Progres Pesanan');
    }

    public function test_user_can_send_message_and_receive_assistant_answer(): void
    {
        $ragMock = Mockery::mock(RagService::class);
        $ragMock->shouldReceive('askQuestion')
            ->once()
            ->andReturn([
                'answer' => "Kami menyediakan 3 skema maklon:\n• CMT murni\n• Semi-CMT\n• Full Package",
                'sources' => [],
                'model_id' => 'test-model',
                'session_id' => 'session-123',
            ]);

        $this->app->instance(RagService::class, $ragMock);

        Livewire::test('chat-modal', ['mode' => 'general'])
            ->call('openChat')
            ->set('currentMessage', 'Apa saja skema maklon?')
            ->call('sendMessage')
            ->assertSee('Apa saja skema maklon?')
            ->assertSee('Kami menyediakan 3 skema maklon')
            ->assertSee('CMT murni')
            ->assertSee('Semi-CMT');
    }

    public function test_user_can_click_quick_suggestion(): void
    {
        $ragMock = Mockery::mock(RagService::class);
        $ragMock->shouldReceive('askQuestion')
            ->once()
            ->andReturn([
                'answer' => 'Sample dibuat dalam waktu 3-5 hari kerja.',
                'sources' => [],
                'model_id' => 'test-model',
                'session_id' => 'session-456',
            ]);

        $this->app->instance(RagService::class, $ragMock);

        Livewire::test('chat-modal', ['mode' => 'general'])
            ->call('openChat')
            ->call('selectSuggestion', 'Bagaimana alur pembuatan sample pakaian?')
            ->assertSee('Bagaimana alur pembuatan sample pakaian?')
            ->assertSee('Sample dibuat dalam waktu 3-5 hari kerja.');
    }

    public function test_chat_modal_strips_source_citations(): void
    {
        $component = Livewire::test('chat-modal', ['mode' => 'general']);

        $rawText = "Tahapan produksi mencakup cutting dan sewing.\n\nSumber: dokumentasi-lukla-ops.md";
        $html = $component->instance()->formatMessage($rawText);

        $this->assertStringContainsString('Tahapan produksi mencakup cutting dan sewing', $html);
        $this->assertStringNotContainsString('Sumber:', $html);
        $this->assertStringNotContainsString('dokumentasi-lukla-ops.md', $html);
    }

    public function test_chat_modal_uses_compact_layout_and_does_not_use_max_w_lg(): void
    {
        Livewire::test('chat-modal', ['mode' => 'general'])
            ->call('openChat')
            ->assertSee('max-w-md')
            ->assertDontSee('max-w-lg');
    }
}
