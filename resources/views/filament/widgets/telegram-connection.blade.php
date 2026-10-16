<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-paper-airplane" class="h-5 w-5 text-primary-500" />
                <span>Telegram</span>
            </div>
        </x-slot>

        <x-slot name="description">
            Terima pembaruan order dan setujui pengajuan langsung dari Telegram.
        </x-slot>

        @php
            $linked = $this->isLinked();
        @endphp

        @if ($linked)
            <p class="text-sm">Akun Telegram Anda sudah tertaut dengan aplikasi ini.</p>
        @elseif ($linkUrl !== null)
            <div class="space-y-2">
                <p class="text-sm">Tautan berlaku 15 menit dan hanya sekali pakai. Buka di perangkat Telegram Anda:</p>
                <a href="{{ $linkUrl }}" class="!text-primary-500" target="_blank" rel="noopener">
                    {{ $linkUrl }}
                </a>
            </div>
        @else
            <div class="space-y-2">
                <p class="text-sm">Akun Telegram Anda belum tertaut.</p>
                <x-filament::button wire:click="generateLink">
                    Hubungkan Telegram
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
