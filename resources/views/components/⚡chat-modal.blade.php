<?php

use App\Services\Ai\RagService;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /** @var 'general'|'portal' */
    public string $mode = 'general';

    /** @var array<int, array{type: string, content: string, timestamp: string}> */
    public array $messages = [];

    public string $currentMessage = '';

    public bool $isLoading = false;

    public bool $isOpen = false;

    /**
     * @param  array<string, mixed>  $clientContext
     */
    public array $clientContext = [];

    /**
     * @param  array<string, mixed>  $clientContext
     */
    public function mount(string $mode = 'general', array $clientContext = []): void
    {
        $this->mode = $mode;
        $this->clientContext = $clientContext;

        $this->messages = [
            [
                'type' => 'assistant',
                'content' => $this->getWelcomeMessage(),
                'timestamp' => now()->format('H:i'),
            ],
        ];
    }

    public function sendMessage(): void
    {
        $userMessage = trim($this->currentMessage);

        if (empty($userMessage)) {
            return;
        }

        // Rate-limit: 20 messages per minute per user / IP.
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts('ai-chat:'.(auth()->id() ?? request()->ip()), 20)) {
            $this->addMessage('assistant', 'Terlalu banyak permintaan. Silakan tunggu sebentar sebelum mengirim pesan berikutnya.');

            return;
        }

        \Illuminate\Support\Facades\RateLimiter::hit('ai-chat:'.(auth()->id() ?? request()->ip()), 60);

        $this->addMessage('user', $userMessage);
        $this->currentMessage = '';
        $this->isLoading = true;

        try {
            /** @var RagService $ragService */
            $ragService = app(RagService::class);
            $response = $ragService->askQuestion($userMessage, 'generate_only', $this->clientContext);

            $assistantMessage = $response['answer'] ?? 'Maaf, saya tidak dapat memberikan respons saat ini.';
            $this->addMessage('assistant', $assistantMessage);
        } catch (\Exception $e) {
            $this->addMessage('assistant', 'Maaf, terjadi kesalahan saat menghubungi asisten AI. Silakan coba beberapa saat lagi.');
            logger()->error('RAG Service error in ChatModal', [
                'error' => $e->getMessage(),
                'user_message' => $userMessage,
                'mode' => $this->mode,
            ]);
        }

        $this->isLoading = false;
        $this->dispatch('chat-scroll-bottom');
    }

    public function selectSuggestion(string $prompt): void
    {
        if ($this->isLoading) {
            return;
        }

        $this->currentMessage = $prompt;
        $this->sendMessage();
    }

    public function clearChat(): void
    {
        $this->messages = [
            [
                'type' => 'assistant',
                'content' => $this->getWelcomeMessage(),
                'timestamp' => now()->format('H:i'),
            ],
        ];
    }

    #[On('open-chat')]
    public function openChat(): void
    {
        $this->isOpen = true;
    }

    #[On('close-chat')]
    public function closeChat(): void
    {
        $this->isOpen = false;
    }

    /**
     * Memformat teks jawaban asisten menjadi HTML berstruktur rapi:
     * - Menghapus penyebutan sumber / referensi agar tidak tampil di chatbot
     * - Mengubah karakter bullet (•, ●, dll) menjadi markdown list
     * - Memisahkan daftar/list ke paragraf baru agar tidak menumpuk dalam 1 baris
     * - Merender markdown aman (bold, italic, list, paragraph break)
     */
    public function formatMessage(string $content): string
    {
        if (trim($content) === '') {
            return '';
        }

        // Pastikan encoding string adalah UTF-8 valid
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = (string) iconv('UTF-8', 'UTF-8//IGNORE', $content);
        }

        // Hapus sitasi sumber/referensi di jawaban agar chatbot tidak mencantumkan sumber
        $content = preg_replace('/(?:\r?\n|^)[ \t]*(?:\*{1,2}|_{1,2})?(?:sumber|source|referensi)s?(?:\*{1,2}|_{1,2})?\s*[:：].*$/miu', '', $content);
        $content = preg_replace('/(?:\r?\n|^)[ \t]*\[?(?:sumber|source|referensi)s?\s*[:：][^\]\r\n]+\]?[ \t]*$/miu', '', $content);
        $content = trim((string) $content);

        // 1. Normalisasi karakter bullet Unicode (•, ●, ○, ▪, ⁃) di awal baris menjadi "- "
        $formatted = preg_replace('/^[ \t]*[\x{2022}\x{25CF}\x{25CB}\x{25AA}\x{2043}][ \t]*/mu', '- ', $content);

        // 2. Jika ada list (- / * / 1.) yang langsung menempel setelah baris teks biasa tanpa baris kosong,
        // sisipkan baris kosong agar parser CommonMark memecahnya menjadi blok list <ul> / <ol> yang rapi.
        $formatted = preg_replace('/([^\n])\n([ \t]*(?:[-*]|\d+\.) )/u', "$1\n\n$2", (string) $formatted);

        // 3. Render Markdown ke HTML yang aman (escape HTML tags)
        return (string) Str::markdown((string) $formatted, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'renderer' => [
                'soft_break' => "<br />\n",
            ],
        ]);
    }

    /**
     * @return array<int, array{label: string, prompt: string}>
     */
    public function getQuickSuggestions(): array
    {
        if ($this->mode === 'portal') {
            return [
                ['label' => 'Status Pesanan', 'prompt' => 'Bagaimana progres pesanan saya saat ini?'],
                ['label' => 'Sample Fisik', 'prompt' => 'Apakah sample fisik artikel sudah disetujui?'],
                ['label' => 'Stok Bahan', 'prompt' => 'Bagaimana ketersediaan stok bahan di gudang?'],
            ];
        }

        return [
            ['label' => 'Alur Sample', 'prompt' => 'Bagaimana alur pembuatan sample pakaian?'],
            ['label' => 'Skema CMT', 'prompt' => 'Apa perbedaan skema maklon CMT dan Semi-CMT?'],
            ['label' => 'Tahapan QC', 'prompt' => 'Bagaimana tahapan QC dan standar inspeksi pakaian?'],
        ];
    }

    private function addMessage(string $type, string $content): void
    {
        $this->messages[] = [
            'type' => $type,
            'content' => $content,
            'timestamp' => now()->format('H:i'),
        ];
    }

    private function getWelcomeMessage(): string
    {
        if ($this->mode === 'portal') {
            $clientName = $this->clientContext['client_name'] ?? 'Klien';

            return "Halo **{$clientName}**! Selamat datang di Portal Klien Lukla 👋\n\nSaya asisten cerdas yang siap membantu Anda memantau dan mendapatkan informasi seputar:\n\n• **Status & Progres Pesanan:** Memeriksa progres pengerjaan order aktif\n• **Evaluasi Sample:** Melihat spesifikasi fisik, hasil sample, dan status persetujuan (ACC)\n• **Bahan & Gudang:** Memantau penerimaan material dan ketersediaan kain\n• **Kualitas & QC:** Hasil inspeksi kendali mutu serta catatan reject\n• **Tagihan & Invoice:** Status penerbitan invoice dan konfirmasi pembayaran\n\nAda hal yang bisa saya bantu jelaskan hari ini?";
        }

        return "Halo! Selamat datang di **Lukla Operations Engine** 👋\n\nSaya asisten manufaktur cerdas yang siap membantu menjawab pertanyaan seputar operasional pabrik dan maklon garmen kami:\n\n• **Skema Maklon:** Pilihan kerjasama CMT, Semi-CMT, dan Full Package (CMT + Bahan)\n• **Alur Pembuatan Sample:** Mulai dari pengajuan desain, pembuatan prototipe, hingga persetujuan (ACC)\n• **Proses Produksi Massal:** Cutting presisi, sewing berbasis lini, hingga kontrol kualitas (QC)\n• **Standar Waktu & Kapasitas:** Perhitungan time study per komponen dan estimasi pengerjaan\n\nSilakan tanyakan apa pun yang ingin Anda ketahui seputar layanan produksi kami!";
    }
};
?>

<div
    x-data="{
        x: null,
        y: null,
        isDragging: false,
        startX: 0,
        startY: 0,
        initialLeft: 0,
        initialTop: 0,
        hasMoved: false,
        isMinimized: false,
        startDrag(e) {
            // Hanya drag dengan mouse klik kiri atau touch
            if (e.type === 'mousedown' && e.button !== 0) return;
            // Hindari drag saat mengklik tombol atau input
            if (e.target.closest('button, input, a, textarea')) return;

            const clientX = e.type.startsWith('touch') ? e.touches[0].clientX : e.clientX;
            const clientY = e.type.startsWith('touch') ? e.touches[0].clientY : e.clientY;

            const el = this.$refs.chatWindow;
            if (!el) return;
            const rect = el.getBoundingClientRect();

            this.isDragging = true;
            this.startX = clientX;
            this.startY = clientY;
            this.initialLeft = rect.left;
            this.initialTop = rect.top;
            this.hasMoved = true;
            this.x = rect.left;
            this.y = rect.top;

            const moveHandler = (moveEvent) => {
                if (!this.isDragging) return;
                const curX = moveEvent.type.startsWith('touch') ? moveEvent.touches[0].clientX : moveEvent.clientX;
                const curY = moveEvent.type.startsWith('touch') ? moveEvent.touches[0].clientY : moveEvent.clientY;

                const dx = curX - this.startX;
                const dy = curY - this.startY;

                const winWidth = window.innerWidth;
                const winHeight = window.innerHeight;
                const w = el.offsetWidth;
                const h = el.offsetHeight;

                let newX = this.initialLeft + dx;
                let newY = this.initialTop + dy;

                newX = Math.max(8, Math.min(newX, winWidth - w - 8));
                newY = Math.max(8, Math.min(newY, winHeight - h - 8));

                this.x = newX;
                this.y = newY;
            };

            const stopHandler = () => {
                this.isDragging = false;
                window.removeEventListener('mousemove', moveHandler);
                window.removeEventListener('mouseup', stopHandler);
                window.removeEventListener('touchmove', moveHandler);
                window.removeEventListener('touchend', stopHandler);
            };

            window.addEventListener('mousemove', moveHandler);
            window.addEventListener('mouseup', stopHandler);
            window.addEventListener('touchmove', moveHandler);
            window.addEventListener('touchend', stopHandler);
        },
        resetPosition() {
            this.x = null;
            this.y = null;
            this.hasMoved = false;
        }
    }"
    class="relative"
>
    {{-- Scoped Styles for Modern Intercom/Elfsight-style Chatbot (Matching Image 2) --}}
    <style>
        .lukla-chat-card {
            position: fixed;
            z-index: 50;
            display: flex;
            flex-direction: column;
            width: calc(100vw - 2rem);
            max-width: 400px;
            height: 540px;
            max-height: calc(100vh - 5rem);
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 16px 40px -8px rgba(11, 9, 34, 0.18), 0 4px 16px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            transition: height 0.2s ease, box-shadow 0.2s ease;
        }
        .lukla-chat-card.lukla-chat-dragging {
            box-shadow: 0 24px 50px rgba(8, 122, 120, 0.25);
            border-color: #22b8ad;
        }
        .lukla-chat-card.lukla-chat-minimized {
            height: auto !important;
            max-height: none !important;
        }

        /* Header matching Image 2 */
        .lukla-chat-header {
            background: linear-gradient(135deg, #22b8ad 0%, #087a78 100%);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #ffffff;
            user-select: none;
            cursor: grab;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }
        .lukla-chat-header:active {
            cursor: grabbing;
        }
        .lukla-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            pointer-events: none;
        }
        .lukla-avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #ffffff;
            color: #087a78;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            position: relative;
            flex-shrink: 0;
        }
        .lukla-avatar-circle svg {
            width: 20px;
            height: 20px;
        }
        .lukla-header-title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.01em;
            line-height: 1.2;
        }
        .lukla-header-subtitle {
            margin: 2px 0 0;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .lukla-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #34d399;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.3);
            display: inline-block;
        }
        .lukla-header-actions {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .lukla-header-btn {
            background: rgba(255, 255, 255, 0.15);
            border: none;
            color: #ffffff;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .lukla-header-btn:hover {
            background: rgba(255, 255, 255, 0.28);
            transform: scale(1.05);
        }
        .lukla-header-btn svg {
            width: 16px;
            height: 16px;
        }

        /* Body & Messages area */
        .lukla-chat-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #f4f7fb;
        }
        .lukla-messages-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        /* Message bubbles like Image 2 */
        .lukla-msg-row {
            display: flex;
            width: 100%;
        }
        .lukla-msg-row.lukla-user-side {
            justify-content: flex-end;
        }
        .lukla-msg-row.lukla-ai-side {
            justify-content: flex-start;
        }

        .lukla-bubble-ai {
            max-width: 88%;
            background: #ffffff;
            color: #1e293b;
            padding: 12px 16px;
            border-radius: 18px 18px 18px 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            border: 1px solid #edf2f7;
            font-size: 13.5px;
            line-height: 1.55;
        }
        .lukla-bubble-user {
            max-width: 82%;
            background: linear-gradient(135deg, #22b8ad 0%, #087a78 100%);
            color: #ffffff;
            padding: 10px 16px;
            border-radius: 18px 18px 4px 18px;
            box-shadow: 0 3px 10px rgba(34, 184, 173, 0.25);
            font-size: 13.5px;
            line-height: 1.5;
            font-weight: 500;
        }
        .lukla-time-ai {
            display: block;
            margin-top: 6px;
            font-size: 10.5px;
            color: #94a3b8;
            text-align: right;
        }
        .lukla-time-user {
            display: block;
            margin-top: 4px;
            font-size: 10.5px;
            color: rgba(255, 255, 255, 0.75);
            text-align: right;
        }

        /* Suggestion chips */
        .lukla-chips-container {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
        }
        .lukla-chips-title {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 8px;
            display: block;
        }
        .lukla-chips-flex {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .lukla-chip-btn {
            background: #ffffff;
            border: 1px solid #22b8ad;
            color: #087a78;
            font-size: 11.5px;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 9999px;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(34, 184, 173, 0.1);
        }
        .lukla-chip-btn:hover {
            background: #22b8ad;
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Loading bubble */
        .lukla-loading-card {
            background: #ffffff;
            border-radius: 18px 18px 18px 4px;
            padding: 10px 16px;
            border: 1px solid #edf2f7;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        }
        .lukla-dots {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .lukla-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #22b8ad;
            animation: luklaBounce 1.2s infinite ease-in-out;
        }
        .lukla-dot:nth-child(2) { animation-delay: 0.15s; }
        .lukla-dot:nth-child(3) { animation-delay: 0.3s; }
        @keyframes luklaBounce {
            0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
            40% { transform: translateY(-4px); opacity: 1; }
        }
        .lukla-loading-txt {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }

        /* Footer Input Area */
        .lukla-chat-footer {
            background: #ffffff;
            padding: 12px 14px;
            border-top: 1px solid #edf2f7;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
        }
        .lukla-input-form {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }
        .lukla-input-text {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 9999px;
            padding: 10px 18px;
            font-size: 13.5px;
            color: #0b0922;
            outline: none;
            transition: all 0.2s ease;
        }
        .lukla-input-text::placeholder {
            color: #94a3b8;
        }
        .lukla-input-text:focus {
            background: #ffffff;
            border-color: #22b8ad;
            box-shadow: 0 0 0 3px rgba(34, 184, 173, 0.15);
        }
        .lukla-send-button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #22b8ad;
            border: none;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            box-shadow: 0 3px 8px rgba(34, 184, 173, 0.35);
            transition: all 0.2s ease;
        }
        .lukla-send-button:hover:not(:disabled) {
            background: #087a78;
            transform: scale(1.05);
        }
        .lukla-send-button:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .lukla-send-button svg {
            width: 18px;
            height: 18px;
        }

        /* Floating Trigger Button (Closed State) */
        .lukla-launcher-button {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 40;
            display: flex;
            align-items: center;
            gap: 12px;
            background: #0b0922;
            color: #ffffff;
            padding: 8px 18px 8px 10px;
            border-radius: 9999px;
            border: 2px solid #22b8ad;
            box-shadow: 0 12px 30px rgba(11, 9, 34, 0.25);
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
        }
        .lukla-launcher-button:hover {
            background: #087a78;
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 16px 36px rgba(8, 122, 120, 0.35);
        }
        .lukla-launcher-icon-wrap {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #22b8ad;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            flex-shrink: 0;
        }
        .lukla-launcher-icon-wrap svg {
            width: 22px;
            height: 22px;
        }
        .lukla-launcher-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 18px;
            height: 18px;
            background: #ef4444;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #0b0922;
        }
        .lukla-launcher-text-box {
            display: flex;
            flex-direction: column;
            text-align: left;
        }
        .lukla-launcher-label {
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.2;
        }
        .lukla-launcher-desc {
            font-size: 11px;
            color: #34d399;
            font-weight: 500;
            line-height: 1.2;
            margin-top: 1px;
        }

        /* Scoped Markdown Styles for Chatbot Answers */
        .chat-markdown {
            color: #1e293b;
            word-break: break-word;
        }
        .chat-markdown p {
            margin: 0 0 0.55rem 0;
            line-height: 1.55;
        }
        .chat-markdown p:last-child {
            margin-bottom: 0;
        }
        .chat-markdown ul, .chat-markdown ol {
            margin: 0.35rem 0 0.55rem 0;
            padding-left: 1.2rem;
        }
        .chat-markdown ul { list-style-type: disc; }
        .chat-markdown ol { list-style-type: decimal; }
        .chat-markdown li {
            margin-bottom: 0.3rem;
            line-height: 1.5;
        }
        .chat-markdown li:last-child {
            margin-bottom: 0;
        }
        .chat-markdown strong, .chat-markdown b {
            font-weight: 700;
            color: #0b0922;
        }
        .chat-markdown code {
            font-size: 0.85em;
            padding: 0.15rem 0.35rem;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 0.25rem;
            font-family: monospace;
        }
        .chat-markdown hr {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 0.65rem 0;
        }
    </style>

    {{-- Floating trigger button (when closed) --}}
    @if($mode === 'general' && ! $isOpen)
        <button
            type="button"
            wire:click="openChat"
            class="lukla-launcher-button"
            aria-label="Tanya Asisten AI Lukla"
        >
            <span class="lukla-launcher-icon-wrap">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                </svg>
                <span class="lukla-launcher-badge">1</span>
            </span>

            <span class="lukla-launcher-text-box">
                <span class="lukla-launcher-label">Tanya Asisten AI</span>
                <span class="lukla-launcher-desc">Online · Klik untuk bantuan</span>
            </span>
        </button>
    @endif

    {{-- Chat Window (Clean modern card matching Image 2, compact max-w-md, draggable by header) --}}
    @if($isOpen)
        <div
            x-ref="chatWindow"
            wire:key="chat-modal-window"
            @keydown.escape.window="$wire.closeChat()"
            class="lukla-chat-card max-w-md"
            :class="{
                'lukla-chat-dragging': isDragging,
                'lukla-chat-minimized': isMinimized
            }"
            :style="hasMoved && x !== null ? `left: ${x}px; top: ${y}px; bottom: auto; right: auto; position: fixed; margin: 0;` : `bottom: 1.5rem; right: 1.5rem; position: fixed;`"
            role="dialog"
            aria-modal="false"
            aria-labelledby="chat-modal-title"
        >
            {{-- Header: Styled after Virtual Assistant header in Image 2 --}}
            <div
                @mousedown="startDrag($event)"
                @touchstart="startDrag($event)"
                class="lukla-chat-header"
                title="Tahan dan geser untuk memindahkan chat"
            >
                <div class="lukla-header-left">
                    {{-- Sparkle avatar circle matching Image 2 --}}
                    <div class="lukla-avatar-circle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
                        </svg>
                    </div>

                    <div>
                        <h3 id="chat-modal-title" class="lukla-header-title">
                            @if($mode === 'portal') Asisten Portal Lukla @else Virtual Assistant @endif
                        </h3>
                        <p class="lukla-header-subtitle">
                            <span class="lukla-status-dot"></span> Online · Lukla Operations
                        </p>
                    </div>
                </div>

                {{-- Window Action Controls --}}
                <div class="lukla-header-actions">
                    {{-- Reset Position (when moved) --}}
                    <button
                        type="button"
                        x-show="hasMoved"
                        x-cloak
                        @click="resetPosition()"
                        class="lukla-header-btn"
                        title="Kembalikan ke posisi awal"
                    >
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </button>

                    {{-- Minimize / Expand Toggle --}}
                    <button
                        type="button"
                        @click="isMinimized = !isMinimized"
                        class="lukla-header-btn"
                        :title="isMinimized ? 'Perluas chat' : 'Perkecil chat'"
                    >
                        <svg x-show="!isMinimized" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                        </svg>
                        <svg x-show="isMinimized" x-cloak viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                        </svg>
                    </button>

                    {{-- Close Button matching Image 2 --}}
                    <button
                        type="button"
                        wire:click="closeChat"
                        class="lukla-header-btn"
                        aria-label="Tutup chat"
                    >
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Body (hidden when minimized) --}}
            <div x-show="!isMinimized" class="lukla-chat-body">
                {{-- Messages Container --}}
                <div
                    id="chat-messages-{{ $this->getId() }}"
                    class="lukla-messages-scroll"
                    x-data
                    x-init="$el.scrollTop = $el.scrollHeight"
                    @chat-scroll-bottom.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                >
                    @foreach($messages as $index => $message)
                        <div class="lukla-msg-row {{ $message['type'] === 'user' ? 'lukla-user-side' : 'lukla-ai-side' }}">
                            @if($message['type'] === 'user')
                                <div class="lukla-bubble-user">
                                    <p style="margin: 0; white-space: pre-wrap;">{{ $message['content'] }}</p>
                                    <span class="lukla-time-user">{{ $message['timestamp'] }}</span>
                                </div>
                            @else
                                <div class="lukla-bubble-ai">
                                    <div class="chat-markdown">
                                        {!! $this->formatMessage($message['content']) !!}
                                    </div>
                                    <span class="lukla-time-ai">{{ $message['timestamp'] }}</span>

                                    {{-- Modern Quick Suggestion Chips matching Image 2 --}}
                                    @if($index === 0 && count($messages) === 1 && ! $isLoading)
                                        <div class="lukla-chips-container">
                                            <span class="lukla-chips-title">💡 Topik cepat:</span>
                                            <div class="lukla-chips-flex">
                                                @foreach($this->getQuickSuggestions() as $suggestion)
                                                    <button
                                                        type="button"
                                                        wire:click="selectSuggestion(@js($suggestion['prompt']))"
                                                        class="lukla-chip-btn"
                                                    >
                                                        {{ $suggestion['label'] }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if($isLoading)
                        <div class="lukla-msg-row lukla-ai-side">
                            <div class="lukla-loading-card">
                                <span class="lukla-dots">
                                    <span class="lukla-dot"></span>
                                    <span class="lukla-dot"></span>
                                    <span class="lukla-dot"></span>
                                </span>
                                <span class="lukla-loading-txt">Mengetik jawaban…</span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Input Bar (Styled like Image 2: rounded pill input + circular send button) --}}
                <div class="lukla-chat-footer">
                    <form wire:submit="sendMessage" class="lukla-input-form">
                        <input
                            type="text"
                            wire:model="currentMessage"
                            placeholder="Tulis pesan Anda…"
                            autocomplete="off"
                            @disabled($isLoading)
                            class="lukla-input-text"
                            x-init="$el.focus()"
                        />
                        <button
                            type="submit"
                            @disabled($isLoading)
                            class="lukla-send-button"
                            aria-label="Kirim pertanyaan"
                        >
                            @if($isLoading)
                                <svg style="animation: spin 1s linear infinite;" fill="none" viewBox="0 0 24 24">
                                    <circle opacity="0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path opacity="0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                </svg>
                            @endif
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
