<?php

use App\Services\Ai\RagService;
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
            $this->addMessage('assistant', 'Maaf, terjadi kesalahan. Silakan coba lagi nanti.');
            logger()->error('RAG Service error in ChatModal', [
                'error' => $e->getMessage(),
                'user_message' => $userMessage,
                'mode' => $this->mode,
            ]);
        }

        $this->isLoading = false;
        $this->dispatch('chat-scroll-bottom');
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

            return "Halo {$clientName}! Saya siap membantu Anda dengan informasi seputar order, inventory, QC, dan proses manufaktur. Apa yang bisa saya bantu?";
        }

        return 'Halo! Saya siap membantu menjawab pertanyaan seputar manufaktur dan proses produksi. Apa yang ingin Anda ketahui?';
    }
};
?>

<div>
    {{-- Floating trigger button (only rendered when mode=general and not inside Filament) --}}
    @if($mode === 'general' && ! $isOpen)
        <button
            type="button"
            wire:click="openChat"
            class="fixed bottom-6 right-6 z-40 flex h-14 w-14 items-center justify-center border-2 shadow-lg transition hover:scale-105 focus:outline-none"
            style="background: var(--orange); border-color: var(--orange-dark); color: #fff;"
            aria-label="Buka asisten manufaktur"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
            </svg>
        </button>
    @endif

    {{-- Modal overlay --}}
    @if($isOpen)
        <div
            class="fixed inset-0 z-50 flex items-end justify-end p-4 sm:items-center sm:justify-center"
            wire:key="chat-modal-overlay"
            @keydown.escape.window="$wire.closeChat()"
        >
            {{-- Backdrop --}}
            <div
                class="fixed inset-0 bg-black/40"
                wire:click="closeChat"
                aria-hidden="true"
            ></div>

            {{-- Dialog --}}
            <div
                class="relative z-10 flex w-full max-w-md flex-col overflow-hidden border-2 shadow-2xl"
                style="background: var(--paper); border-color: var(--line); color: var(--ink); height: 560px;"
                role="dialog"
                aria-modal="true"
                aria-labelledby="chat-modal-title"
            >
                {{-- Header --}}
                <div class="flex shrink-0 items-center justify-between border-b px-4 py-3" style="border-color: var(--line);">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center border-2 text-xs font-bold" style="border-color: var(--ink);">AI</div>
                        <div>
                            <p id="chat-modal-title" class="text-sm font-bold">
                                @if($mode === 'portal') Asisten Portal @else Manufacturing Assistant @endif
                            </p>
                            <p class="text-xs" style="color: var(--sage);">
                                @if($mode === 'portal') Order · Inventory · QC @else Informasi umum manufaktur @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        @if(count($messages) > 1)
                            <button
                                type="button"
                                wire:click="clearChat"
                                class="px-2 py-1 text-xs font-medium transition hover:underline"
                                style="color: var(--sage);"
                            >Hapus riwayat</button>
                        @endif
                        <button
                            type="button"
                            wire:click="closeChat"
                            class="flex h-8 w-8 items-center justify-center transition hover:bg-[var(--paper-deep)]"
                            aria-label="Tutup chat"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Messages --}}
                <div
                    id="chat-messages-{{ $this->getId() }}"
                    class="flex-1 overflow-y-auto p-4"
                    x-data
                    x-init="$el.scrollTop = $el.scrollHeight"
                    @chat-scroll-bottom.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                >
                    <div class="space-y-4">
                        @foreach($messages as $message)
                            <div @class(['flex', 'justify-end' => $message['type'] === 'user', 'justify-start' => $message['type'] !== 'user'])>
                                @if($message['type'] === 'user')
                                    <div class="max-w-[80%] px-4 py-2 text-sm text-white" style="background: var(--orange);">
                                        <p class="leading-6">{{ $message['content'] }}</p>
                                        <p class="mt-0.5 text-right text-xs opacity-70">{{ $message['timestamp'] }}</p>
                                    </div>
                                @else
                                    <div class="flex max-w-[85%] items-start gap-2">
                                        <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center border text-[10px] font-bold" style="border-color: var(--ink);">AI</div>
                                        <div class="border px-4 py-2 text-sm" style="border-color: var(--line); background: var(--paper-deep);">
                                            <p class="leading-6">{{ $message['content'] }}</p>
                                            <p class="mt-0.5 text-xs" style="color: var(--sage);">{{ $message['timestamp'] }}</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if($isLoading)
                            <div class="flex justify-start">
                                <div class="flex items-start gap-2">
                                    <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center border text-[10px] font-bold" style="border-color: var(--ink);">AI</div>
                                    <div class="border px-4 py-3" style="border-color: var(--line); background: var(--paper-deep);">
                                        <span class="flex items-center gap-1.5 text-xs" style="color: var(--sage);">
                                            <span class="inline-block h-1.5 w-1.5 animate-bounce rounded-full" style="background: var(--sage);"></span>
                                            <span class="inline-block h-1.5 w-1.5 animate-bounce rounded-full" style="background: var(--sage); animation-delay: 150ms;"></span>
                                            <span class="inline-block h-1.5 w-1.5 animate-bounce rounded-full" style="background: var(--sage); animation-delay: 300ms;"></span>
                                            <span class="ml-1">Memproses…</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Input --}}
                <div class="shrink-0 border-t px-4 py-3" style="border-color: var(--line);">
                    <form wire:submit="sendMessage" class="flex gap-2">
                        <input
                            type="text"
                            wire:model="currentMessage"
                            placeholder="Ketik pertanyaan…"
                            autocomplete="off"
                            @disabled($isLoading)
                            class="flex-1 border px-3 py-2 text-sm focus:outline-none"
                            style="border-color: var(--line); background: var(--paper); color: var(--ink);"
                            x-init="$el.focus()"
                        />
                        <button
                            type="submit"
                            @disabled($isLoading)
                            class="border px-4 py-2 text-sm font-semibold transition disabled:opacity-40"
                            style="border-color: var(--orange); color: var(--orange-dark);"
                            onmouseover="this.style.background='var(--orange)';this.style.color='#fff';"
                            onmouseout="this.style.background='';this.style.color='var(--orange-dark)';"
                        >
                            @if($isLoading)
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            @else
                                Kirim
                            @endif
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
