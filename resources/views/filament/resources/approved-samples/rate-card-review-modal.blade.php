@php
    $components = $record->article?->components ?? collect();
    $revision = $record->approvedRevision;
    $timeStudies = $revision ? $revision->timeStudies->keyBy('component_id') : collect();
    $totalRate = $record->totalSewingRate();
@endphp

<div class="space-y-4">
    <div class="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 rounded-lg text-xs text-amber-800 dark:text-amber-300">
        <p class="font-semibold">Pemeriksaan Usulan Tarif Borongan (Rate Card)</p>
        <p class="mt-0.5 text-amber-700 dark:text-amber-400">
            Berikut adalah rincian tarif borongan yang diajukan oleh Kepala Produksi berdasarkan durasi Time Study (SMV).
            Klik <strong>Setujui</strong> untuk mengesahkan tarif aktif, atau klik <strong>Tolak Tarif</strong> jika nominal melebihi plafon biaya.
        </p>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs">
        <table class="w-full text-left text-xs text-gray-700 dark:text-gray-300">
            <thead class="bg-gray-50 dark:bg-gray-800/60 uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800">
                <tr>
                    <th scope="col" class="py-2.5 px-3 w-10 text-center">No</th>
                    <th scope="col" class="py-2.5 px-3">Komponen</th>
                    <th scope="col" class="py-2.5 px-3">Time Study (SMV)</th>
                    <th scope="col" class="py-2.5 px-3 text-right">Usulan Tarif</th>
                    <th scope="col" class="py-2.5 px-3 text-center">Status</th>
                    <th scope="col" class="py-2.5 px-3">Catatan Usulan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse($components as $component)
                    @php
                        $ts = $timeStudies->get($component->id);
                        $rateCard = \App\Models\RateCard::query()
                            ->withoutGlobalScope('client')
                            ->where('client_id', $record->client_id)
                            ->where('component_id', $component->id)
                            ->latest('effective_from')
                            ->first();
                    @endphp
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="py-2.5 px-3 text-center font-medium text-gray-500">
                            {{ $component->sequence }}
                        </td>
                        <td class="py-2.5 px-3">
                            <span class="font-semibold text-gray-900 dark:text-gray-100 block">{{ $component->name }}</span>
                            <span class="font-mono text-[10px] text-gray-400">{{ $component->code }}</span>
                        </td>
                        <td class="py-2.5 px-3">
                            @if($ts)
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $ts->duration_seconds }} dtk</span>
                                <span class="text-[10px] text-gray-400">({{ round($ts->duration_seconds / 60, 2) }} mnt)</span>
                            @else
                                <span class="text-gray-400 italic">Belum diuji</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-right font-mono font-semibold text-gray-900 dark:text-gray-100">
                            @if($rateCard)
                                Rp {{ number_format((float) $rateCard->rate, 0, ',', '.') }}
                            @else
                                <span class="text-gray-400 italic">Belum diisi</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            @if($rateCard)
                                @if($rateCard->status === \App\Enums\RateCardStatus::APPROVED)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-400">
                                        Disetujui
                                    </span>
                                @elseif($rateCard->status === \App\Enums\RateCardStatus::PENDING)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-400">
                                        Menunggu
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-400">
                                        Ditolak
                                    </span>
                                @endif
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400 text-[11px] max-w-xs truncate" title="{{ $rateCard?->notes }}">
                            {{ $rateCard?->notes ?: '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-4 text-center text-gray-400 italic">
                            Tidak ada data komponen pada artikel ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($components->isNotEmpty())
                <tfoot class="bg-gray-50/80 dark:bg-gray-800/80 border-t border-gray-200 dark:border-gray-800 font-semibold text-gray-900 dark:text-gray-100">
                    <tr>
                        <td colspan="3" class="py-2.5 px-3 text-right">
                            Total Tarif Borongan Jahit:
                        </td>
                        <td class="py-2.5 px-3 text-right font-mono text-emerald-600 dark:text-emerald-400">
                            Rp {{ number_format($totalRate, 0, ',', '.') }}
                        </td>
                        <td colspan="2" class="py-2.5 px-3 text-[11px] text-gray-500">
                            / pcs (potong jadi)
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
