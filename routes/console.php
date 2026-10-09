<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Spec 6.6 phase 2 (timezone is Asia/Riyadh via config/app.php).
Schedule::command('suggestions:rebuild')->dailyAt('03:00')->withoutOverlapping();

// Spec 7: delete payment screenshots 90 days after delivery.
Schedule::command('payments:cleanup-proofs')->dailyAt('03:30')->withoutOverlapping();