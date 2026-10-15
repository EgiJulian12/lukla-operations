{{--
    Stopwatch pengerjaan penjahit pada satu tugas komponen.

    "Mulai" menstempel Waktu Mulai lalu menjalankan pencacah; "Stop" membekukan
    Waktu Selesai. Selama berjalan Waktu Selesai ikut bergerak, sehingga laporan
    yang dikirim tanpa menekan Stop tetap membawa waktu kirim sebagai akhir
    pengerjaan.

    Waktu distempel dalam zona aplikasi (config app.timezone), bukan zona
    peramban: seluruh stempel waktu lain di aplikasi ini — termasuk default
    `now()` pada formulir ini — lahir dari jam server, dan mencampur keduanya
    membuat durasi pengerjaan melompat sebesar selisih zona.

    Nilai ditulis ke state Livewire dengan `$set(..., live = false)`: lokal,
    tanpa permintaan ke server, dan ikut terkirim pada permintaan berikutnya —
    termasuk saat formulir dikirim. Pencerminan ke input Waktu Mulai / Waktu
    Selesai hanya untuk tampilan.

    Stopwatch juga menandai keadaannya sendiri lewat `work_timer_stopped`:
    hanya terisi selama pengerjaan dihentikan. Penanda itulah yang memunculkan
    field Jumlah Selesai dan Catatan sekaligus membuka tombol kirim modal
    (lihat tombol "Kerjakan" di ListOutputEntries), sehingga laporan hanya bisa
    dikirim saat pengerjaan benar-benar sudah dihentikan.

    State path dihitung dari path field ini, sehingga stopwatch tetap benar
    walau dipasang di dalam modal aksi maupun di dalam form biasa.
--}}
@php
    $statePathPrefix = \Illuminate\Support\Str::beforeLast((string) $getStatePath(), '.');
@endphp

<div
    x-data="{
        running: false,
        seconds: 0,
        timer: null,
        startedAt: null,
        startStatePath: '{{ $statePathPrefix }}.interval_start',
        endStatePath: '{{ $statePathPrefix }}.interval_end',
        stoppedStatePath: '{{ $statePathPrefix }}.work_timer_stopped',

        formatDuration(total) {
            const hours = Math.floor(total / 3600);
            const minutes = Math.floor((total % 3600) / 60).toString().padStart(2, '0');
            const secs = (total % 60).toString().padStart(2, '0');

            return hours > 0 ? `${hours}:${minutes}:${secs}` : `${minutes}:${secs}`;
        },

        formatStamp(date) {
            const pad = (value) => String(value).padStart(2, '0');

            return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}T${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}:${pad(date.getUTCSeconds())}`;
        },

        writeTime(statePath, date) {
            const stamp = this.formatStamp(date);

            if (typeof $wire !== 'undefined') {
                $wire.$set(statePath, stamp, false);
            }

            this.mirrorInput(statePath, stamp);
        },

        mirrorInput(statePath, stamp) {
            const field = statePath.split('.').pop();
            const scope = (typeof $el !== 'undefined' && $el.closest('.fi-modal')) || document;
            const input = scope.querySelector(`input[wire\\:model='${statePath}']`)
                || scope.querySelector(`input[id$='${field}']`);

            if (! input) {
                return;
            }

            input.value = stamp;

            // Peramban boleh memangkas detik yang bernilai nol pada input
            // `datetime-local`; bentuk itu menunjuk waktu yang sama, jadi
            // nilainya diteruskan juga. Bila peramban menolak nilai berdetik
            // sama sekali, input dibiarkan apa adanya — state Livewire sudah
            // memegang waktu yang benar dan itulah yang terkirim.
            if (input.value !== stamp && input.value !== stamp.slice(0, -3)) {
                return;
            }

            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },

        writeStopped(isStopped) {
            // Ditulis sebagai '1' / '' dan bukan true / false: nilai ini juga
            // dibaca lewat input tersembunyi milik field-nya, dan teks melewati
            // perjalanan itu apa adanya — boolean false yang menjadi teks akan
            // justru terbaca sebagai benar (truthy).
            if (typeof $wire !== 'undefined') {
                $wire.$set(this.stoppedStatePath, isStopped ? '1' : '', false);
            }
        },

        start() {
            if (this.running) {
                return;
            }

            const now = new Date();

            if (this.startedAt === null) {
                this.startedAt = now;
                this.seconds = 0;
                this.writeTime(this.startStatePath, now);
            }

            this.writeTime(this.endStatePath, now);
            this.running = true;

            // Pengerjaan berjalan lagi, jadi laporan masih ditutup: Jumlah
            // Selesai dan Catatan menghilang dan tombol kirim terkunci kembali.
            this.writeStopped(false);

            this.timer = setInterval(() => {
                this.seconds++;
                this.writeTime(this.endStatePath, new Date());
            }, 1000);
        },

        stop() {
            if (! this.running) {
                return;
            }

            this.running = false;
            clearInterval(this.timer);
            this.timer = null;
            this.writeTime(this.endStatePath, new Date());

            // Hanya di titik ini laporan boleh dikirim.
            this.writeStopped(true);
        },

        reset() {
            // Modal dibuka untuk tugas lain: state Livewire kembali kosong,
            // jadi pencacah dan tombolnya ikut kembali ke awal. Menyamakan
            // keadaan dengan state itu membuat komponen tidak pernah membawa
            // pengerjaan tugas sebelumnya.
            this.running = false;
            clearInterval(this.timer);
            this.timer = null;
            this.startedAt = null;
            this.seconds = 0;
            this.writeStopped(false);
        },

        destroy() {
            // Modal ditutup sementara pencacah masih jalan — mis. pengerjaan
            // dibatalkan. Elemennya sudah lewat, tapi timer-nya masih hidup dan
            // terus menulis ke path state milik modal yang sudah tidak
            // terpasang, jadi harus dimatikan di sini.
            clearInterval(this.timer);
            this.timer = null;
        },
    }"
    x-effect="if (! $wire.get(startStatePath) && startedAt !== null) reset()"
    class="flex flex-col gap-3"
    style="box-sizing: border-box; display: flex; width: 100%; min-width: 0; flex-direction: column; gap: 0.75rem;"
>
    <div
        class="flex items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white py-4 shadow-inner dark:border-gray-700 dark:bg-gray-900"
        style="box-sizing: border-box; display: flex; width: 100%; min-width: 0; align-items: center; justify-content: center; gap: 0.75rem; padding: 1rem 0.75rem;"
    >
        <svg
            width="32"
            height="32"
            class="animate-pulse text-emerald-500"
            x-show="running"
            style="display: none;"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>

        <span
            class="font-mono font-bold text-gray-800 dark:text-gray-100"
            style="font-size: clamp(2rem, 9vw, 3rem); font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums;"
            x-text="formatDuration(seconds)"
        >00:00</span>

        <svg
            width="32"
            height="32"
            class="text-gray-300 dark:text-gray-600"
            x-show="! running"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
    </div>

    <p
        class="text-center text-xs font-medium text-emerald-600 dark:text-emerald-400"
        x-show="running"
        style="display: none;"
    >
        Sedang dikerjakan…
    </p>
    <p
        class="text-center text-xs font-medium text-gray-500 dark:text-gray-400"
        x-show="! running && startedAt !== null"
        style="display: none;"
    >
        Pengerjaan dihentikan.
    </p>

    <button
        type="button"
        x-on:click.prevent="running ? stop() : start()"
        x-bind:style="{ backgroundColor: running ? '#f59e0b' : '#059669' }"
        class="inline-flex w-full cursor-pointer items-center justify-center font-bold text-white shadow-md transition hover:brightness-110 active:scale-[0.99]"
        style="box-sizing: border-box; display: flex; width: 100%; max-width: 100%; min-width: 0; min-height: clamp(3.5rem, 12vw, 4.75rem); align-items: center; justify-content: center; gap: 0.75rem; padding: 1rem 1.25rem; font-size: clamp(1.25rem, 4.5vw, 1.5rem); font-weight: 700; line-height: 1.25; text-align: center; color: #ffffff; background-color: #059669; border: 0; border-radius: 0.75rem; cursor: pointer;"
    >
        <svg
            width="32"
            height="32"
            class="shrink-0"
            x-show="! running"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>

        <svg
            width="32"
            height="32"
            class="shrink-0"
            x-show="running"
            style="display: none;"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6"></path>
        </svg>

        <span x-text="running ? 'Stop' : (startedAt === null ? 'Mulai' : 'Lanjutkan')">Mulai</span>
    </button>

    <p class="text-center text-xs text-gray-500 dark:text-gray-400">
        Tekan <span class="font-semibold">Mulai</span> saat pengerjaan dimulai, lalu
        <span class="font-semibold">Stop</span> saat selesai. Jumlah selesai dan catatan muncul setelah pengerjaan
        dihentikan, dan laporan baru bisa dikirim saat itu.
    </p>
</div>
