<?php

use App\Jobs\StoreLiveScoreJob;
use App\Models\Sport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ProcessCricbuzzScrape;

// Show an inspiring quote
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Basic log
// Schedule::call(function () {
//     Log::info('Scheduled task running at ' . now()->toDateTimeString());
// })->everyFiveMinutes()->name('log-message');

// // Correct way to schedule StoreLiveScoreJob dynamically
// Schedule::call(function () {
//     if (Sport::count() === 0) {
//         Log::warning('No sports found in the database. Please seed the sports table.');
//         return;
//     }

//     Sport::all()->each(function (Sport $sport) {
//         dispatch(new StoreLiveScoreJob($sport));
//     });
// })->everyMinute()->name('schedule-live-scores')->timezone('Asia/Kathmandu');


Schedule::call(function () {
    if (!app()->runningInConsole()) {
        return;
    }

    Log::info('Checking and dispatching ScrapeCricbuzzJob...');

    dispatch(new ProcessCricbuzzScrape());

})->everyFiveMinutes()
  ->name('scrape-cricbuzz-job')
  ->withoutOverlapping()
  ->timezone('Asia/Kathmandu');
