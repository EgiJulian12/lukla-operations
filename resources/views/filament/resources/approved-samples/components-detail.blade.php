@php
    $sample = $record ?? (isset($getRecord) && is_callable($getRecord) ? $getRecord() : null);
    $revision = $sample?->approvedRevision;
    $timeStudies = $revision ? $revision->timeStudies->keyBy('component_id') : collect();
    $components = $sample?->article ? $sample->article->components : collect();
    $user = auth()->user();
    $isProductionLead = (bool) $user?->hasRoleSatisfying(fn (\App\Enums\UserRole $role): bool => $role === \App\Enums\UserRole::PRODUCTION_LEAD);
    $canApproveReject = (bool) $user?->hasRoleSatisfying(fn (\App\Enums\UserRole $role): bool => $role->canConfigureSystem() || $role->canManagePayroll());
@endphp

<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm">
    <table class="w-full text-left text-sm text-gray-700 dark:text-gray-300">
        <thead class="bg-gray-50 dark:bg-gray-800/60 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800">
            <tr>
                <th scope="col" class="py-3 px-4 w-12 text-center">Urutan</th>
                <th scope="col" class="py-3 px-4">Kode</th>
                <th scope="col" class="py-3 px-4">Tahapan Jahit (Komponen)</th>
                <th scope="col" class="py-3 px-4">Durasi Time Study</th>
                <th scope="col" class="py-3 px-4">Tarif Borongan (Rate Card)</th>
                <th scope="col" class="py-3 px-4">Status Tarif</th>
                <th scope="col" class="py-3 px-4 text-center">Aksi Tarif</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
            @forelse($components as $component)
                @php
                    $ts = $timeStudies->get($component->id);
                    $rateCard = \App\Models\RateCard::query()
                        ->withoutGlobalScope('client')
                        ->where('client_id', $sample?->client_id)
                        ->where('component_id', $component->id)
                        ->latest('effective_from')
                        ->first();
                @endphp
                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                    <td class="py-3 px-4 text-center font-medium text-gray-500">
                        {{ $component->sequence }}
                    </td>
                    <td class="py-3 px-4 font-mono text-xs font-semibold text-gray-600 dark:text-gray-400">
                        {{ $component->code }}
                    </td>
                    <td class="py-3 px-4 font-semibold text-gray-900 dark:text-gray-100">
                        {{ $component->name }}
                    </td>
                    <td class="py-3 px-4">
                        @if($ts)
                            <div class="flex items-center gap-1.5 font-medium">
                                <span>{{ $ts->duration_seconds }} detik</span>
                                <span class="text-xs text-gray-400 font-normal">({{ round($ts->duration_seconds / 60, 2) }} mnt)</span>
                            </div>
                        @else
                            <span class="text-xs italic text-gray-400">Belum diuji</span>
                        @endif
                    </td>
                    <td class="py-3 px-4">
                        @if($rateCard)
                            <span class="font-mono font-semibold text-gray-900 dark:text-gray-100">
                                Rp {{ number_format($rateCard->rate, 0, ',', '.') }}
                            </span>
                        @else
                            <span class="text-xs italic text-gray-400">Belum ada tarif</span>
                        @endif
                    </td>
                    <td class="py-3 px-4">
                        @if($rateCard)
                            <div class="space-y-1">
                                @if($rateCard->status === \App\Enums\RateCardStatus::APPROVED)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-400">
                                        Disetujui
                                    </span>
                                @elseif($rateCard->status === \App\Enums\RateCardStatus::PENDING)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-400">
                                        Menunggu Persetujuan
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-400">
                                        Ditolak / Minta Revisi
                                    </span>
                                @endif

                                @if($rateCard->status === \App\Enums\RateCardStatus::REJECTED && filled($rateCard->notes))
                                    <p class="text-xs text-rose-600 dark:text-rose-400 font-normal max-w-xs truncate" title="{{ $rateCard->notes }}">
                                        {{ $rateCard->notes }}
                                    </p>
                                @endif
                            </div>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                Belum Diinput
                            </span>
                        @endif
                    </td>
                    <td class="py-3 px-4 text-center">
                        @if($isProductionLead)
                            {{-- Kepala Produksi: Menentukan atau Mengubah Usulan Harga --}}
                            <button
                                type="button"
                                wire:click="mountAction('editRate', { componentId: {{ $component->id }} })"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-md bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-900/60 border border-amber-300 dark:border-amber-700 transition-colors shadow-xs"
                                title="Atur atau usulkan tarif borongan komponen ini"
                            >
                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                </svg>
                                <span>Atur Tarif</span>
                            </button>
                        @elseif($canApproveReject)
                            {{-- Finance & Owner: Approve atau Reject Usulan Harga --}}
                            @if($rateCard)
                                <div class="inline-flex items-center gap-1.5">
                                    @if($rateCard->status !== \App\Enums\RateCardStatus::APPROVED)
                                        <button
                                            type="button"
                                            wire:click="mountAction('approveRate', { componentId: {{ $component->id }} })"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-md bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-900/60 border border-emerald-300 dark:border-emerald-700 transition-colors shadow-xs"
                                            title="Setujui tarif borongan ini"
                                        >
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                            <span>Setujui</span>
                                        </button>
                                    @endif

                                    @if($rateCard->status === \App\Enums\RateCardStatus::PENDING)
                                        <button
                                            type="button"
                                            wire:click="mountAction('rejectRate', { componentId: {{ $component->id }} })"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-md bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-900/60 border border-rose-300 dark:border-rose-700 transition-colors shadow-xs"
                                            title="Tolak atau minta revisi tarif"
                                        >
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                            </svg>
                                            <span>Tolak</span>
                                        </button>
                                    @endif

                                    @if($rateCard->status === \App\Enums\RateCardStatus::APPROVED)
                                        <span class="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                            Disetujui
                                        </span>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-gray-400 italic">Belum Ada Usulan</span>
                            @endif
                        @else
                            <span class="text-xs text-gray-400 italic">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="py-6 px-4 text-center text-sm text-gray-400 italic">
                        Belum ada data tahapan jahit untuk artikel ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
