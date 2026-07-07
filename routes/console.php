<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Schedule::command('reminder:send-wa')->dailyAt('08:00');
Schedule::command('reminder:send-telegram')->dailyAt('08:00');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
