<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon
                    icon="heroicon-o-chat-bubble-left-right"
                    class="h-5 w-5 text-primary-500"
                />
                <span>Asisten AI</span>
            </div>
        </x-slot>

        <x-slot name="description">
            Tanyakan apa saja seputar order, inventory, QC, atau proses manufaktur Anda.
        </x-slot>

        <livewire:chat-modal
            mode="portal"
            :clientContext="$clientContext"
        />
    </x-filament::section>
</x-filament-widgets::widget>
