@php
    /** @var \App\Models\User|null $user */
    $user = filament()->auth()->user();
@endphp

@if ($user)
    @php
        $primaryRole = $user->primaryRole();
        $roleLabel = $primaryRole?->getShortLabel() ?? $user->primaryRoleLabel();
        $roleColor = $primaryRole?->getColor() ?? 'gray';
        $clientName = $user->client?->name;
    @endphp

    <div class="hidden md:flex items-center gap-x-2.5 px-3 py-1 rounded-lg bg-gray-50/80 dark:bg-white/5 border border-gray-200/80 dark:border-white/10 shadow-xs mr-2 transition">
        <div class="flex flex-col text-right">
            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 leading-tight">
                {{ $user->name }}
            </span>
            @if ($clientName && $user->isClientUser())
                <span class="text-[10px] text-gray-500 dark:text-gray-400 leading-none truncate max-w-[140px]" title="{{ $clientName }}">
                    {{ $clientName }}
                </span>
            @endif
        </div>

        <x-filament::badge :color="$roleColor" size="xs">
            {{ $roleLabel }}
        </x-filament::badge>
    </div>
@endif
