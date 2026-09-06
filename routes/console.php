<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('exoplanet:expire-uploads')->hourly();
Schedule::command('exoplanet:prune-tokens')->daily();
Schedule::command('queue:prune-failed --hours=168')->weekly();
