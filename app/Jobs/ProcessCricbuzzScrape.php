<?php
namespace App\Jobs;

use App\Services\CricbuzzScraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Jobs\Cricbuzz\ProcessSeriesListJob;

class ProcessCricbuzzScrape implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CricbuzzScraper $scraper): void
    {
        Log::info('Starting main Cricbuzz scraping job...');
        try {
            $series = $scraper->fetchSeriresOrTournament();
            if (empty($series)) return;

            // Dispatch new job to handle series
            ProcessSeriesListJob::dispatch($series)->onQueue('cricbuzz');

            Log::info('Dispatched ProcessSeriesListJob');
        } catch (\Exception $e) {
            Log::error("Main job failed: {$e->getMessage()}");
        }
    }
}
