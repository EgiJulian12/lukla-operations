<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Jadwal Otomatis
|--------------------------------------------------------------------------
|
| Pastikan scheduler Laravel berjalan di server dengan cron:
|   * * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Tutup periode payroll setiap Sabtu 00:00 WIB (Req 8.1, Modul 10B)
Schedule::command('payroll:close-weekly')
    ->weekly()
    ->saturdays()
    ->at('00:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->runInBackground();
