<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Timezone is Asia/Riyadh via config/app.php.
Schedule::command('queue:work --stop-when-empty --max-time=55')->everyMinute()->withoutOverlapping();
Schedule::command('backup:database')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('suggestions:rebuild')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('payments:cleanup-proofs')->dailyAt('03:30')->withoutOverlapping();