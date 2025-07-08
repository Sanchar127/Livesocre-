<?php
// routes/console.php

use App\Jobs\FetchCricketMatchesJob;
use App\Jobs\StoreLiveScoreJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Http;

// Existing inspire command
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Log message task
Schedule::call(function () {
    Log::info('This is a scheduled task running at ' . now()->toDateTimeString());
})->everyFiveMinutes()->name('log-message');

// Schedule FetchCricketMatchesJob
//Schedule::job(new FetchCricketMatchesJob)->everyMinute()->name('fetch-cricket-matches')->timezone('Asia/Kathmandu');
// Schedule::call(function () {
//     $yourApiUrl = 'https://api.cricapi.com/v1/currentMatches?apikey=5b5c582d-bb03-4243-a539-028aa954ecf7';
//     $data = Http::get($yourApiUrl)->json();
//     dispatch(new StoreLiveScoreJob($data));
// })->everyMinute()->name('store-live-score')->timezone('Asia/Kathmandu');

// Schedule the self-contained job
Schedule::job(new StoreLiveScoreJob)
    ->everyMinute()
    ->name('process-live-scores')
    ->timezone('Asia/Kathmandu')
    ->withoutOverlapping();