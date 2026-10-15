<div x-data="{
    seconds: 0,
    running: false,
    timer: null,
    formatTime(sec) {
        const m = Math.floor(sec / 60).toString().padStart(2, '0');
        const s = (sec % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    },
    start() {
        if (this.running) return;
        this.running = true;
        this.timer = setInterval(() => {
            this.seconds++;
            this.syncDuration();
        }, 1000);
    },
    pause() {
        this.running = false;
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        this.syncDuration();
    },
    reset() {
        this.pause();
        this.seconds = 0;
        this.syncDuration();
    },
    syncDuration() {
        const row = $el.closest('[data-repeater-item]') 
            || $el.closest('.fi-fo-repeater-item') 
            || $el.closest('.grid') 
            || $el.parentElement.parentElement;

        if (row) {
            const input = row.querySelector('input[name*=\'duration_seconds\']') 
                || row.querySelector('input[id*=\'duration_seconds\']')
                || row.querySelector('input[type=\'number\']');

            if (input) {
                input.value = this.seconds;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    },
    init() {
        this.$nextTick(() => {
            const row = $el.closest('[data-repeater-item]') 
                || $el.closest('.fi-fo-repeater-item') 
                || $el.closest('.grid') 
                || $el.parentElement.parentElement;

            if (row) {
                const input = row.querySelector('input[name*=\'duration_seconds\']') 
                    || row.querySelector('input[id*=\'duration_seconds\']')
                    || row.querySelector('input[type=\'number\']');

                if (input && input.value) {
                    this.seconds = parseInt(input.value, 10) || 0;
                }
            }
        });
    }
}" class="flex h-9 items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-2 shadow-xs dark:border-gray-700 dark:bg-gray-800/80">
    <!-- Digital Counter Badge -->
    <div class="flex items-center gap-1.5 rounded border border-gray-300 bg-white px-2 py-0.5 font-mono text-xs font-bold text-gray-800 shadow-inner dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
        <svg width="14" height="14" style="width: 14px; height: 14px; min-width: 14px; min-height: 14px;" class="text-emerald-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="running" style="display: none;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <svg width="14" height="14" style="width: 14px; height: 14px; min-width: 14px; min-height: 14px;" class="text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="!running">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span x-text="formatTime(seconds)">00:00</span>
    </div>

    <!-- Action Buttons -->
    <div class="flex items-center gap-1">
        <button type="button" 
            x-show="!running" 
            @click.prevent="start()" 
            class="inline-flex cursor-pointer items-center gap-1 rounded bg-emerald-600 px-2 py-1 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-500">
            <svg width="12" height="12" style="width: 12px; height: 12px; min-width: 12px; min-height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
            </svg>
            Mulai
        </button>

        <button type="button" 
            x-show="running" 
            @click.prevent="pause()" 
            class="inline-flex cursor-pointer items-center gap-1 rounded bg-amber-500 px-2 py-1 text-xs font-semibold text-white shadow-xs transition hover:bg-amber-400"
            style="display: none;">
            <svg width="12" height="12" style="width: 12px; height: 12px; min-width: 12px; min-height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6"></path>
            </svg>
            Jeda
        </button>

        <button type="button" 
            @click.prevent="reset()" 
            class="inline-flex cursor-pointer items-center rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 shadow-xs transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
            Reset
        </button>
    </div>
</div>
