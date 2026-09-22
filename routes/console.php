<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep website galleries in step with Flickr albums. Needs the Laravel scheduler cron entry:
//   * * * * * cd /path/to/site && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('app:flickr-sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->when(static fn(): bool => flickr_enabled() && (bool) config('services.flickr.auto_sync', true));
