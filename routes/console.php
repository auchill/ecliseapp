<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('mobilesentrix:sync-categories')
    ->dailyAt('02:15')
    ->when(fn () => (bool) config('mobilesentrix.sync_enabled'))
    ->withoutOverlapping();

Schedule::command('mobilesentrix:sync-parts-full')
    ->everyFourHours()
    ->when(fn () => (bool) config('mobilesentrix.sync_enabled'))
    ->withoutOverlapping();
