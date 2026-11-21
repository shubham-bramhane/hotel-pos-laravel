<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly 25% Cloud Sync & AI Analytics refresh (runs at 01:00 AM daily via Windows Task Scheduler / cron)
Schedule::command('pos:sync-cloud --days=3 --ai')
    ->dailyAt((string) config('pos.cloud_sync.nightly_time', '01:00'))
    ->withoutOverlapping();
