<div class="flex items-center gap-3">
    <button
        type="button"
        wire:click="toggleShowAll"
        class="fi-link inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-500 hover:underline dark:text-primary-400"
    >
        @if ($this->showAll ?? false)
            <x-filament::icon icon="heroicon-m-eye-slash" class="h-4 w-4 inline" />
            <span>Sembunyikan Semua</span>
        @else
            <x-filament::icon icon="heroicon-m-eye" class="h-4 w-4 inline" />
            <span>Tampilkan Semua Data</span>
        @endif
    </button>

    <span class="text-gray-300 dark:text-gray-600">|</span>

    <button
        type="button"
        wire:click="resetTableFiltersForm"
        class="fi-link inline-flex items-center gap-1 text-sm font-medium text-danger-600 hover:text-danger-500 hover:underline dark:text-danger-400"
    >
        <x-filament::icon icon="heroicon-m-arrow-path" class="h-4 w-4 inline" />
        <span>Reset Filter</span>
    </button>
</div>
