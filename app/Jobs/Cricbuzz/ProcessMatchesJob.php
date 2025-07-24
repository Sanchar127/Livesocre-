<?php

namespace App\Jobs\Cricbuzz;

use App\Services\CricbuzzScraper;
use App\Services\CricketDataProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMatchesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $maxExceptions = 3;
    public $timeout = 600;
    public $backoff = [60, 120, 300];

    public function __construct(
        public string $seriesId,
        public string $seriesSlug,
        public int $sportId = 1
    ) {}

    public function handle(CricbuzzScraper $scraper, CricketDataProcessor $processor): void
    {
        try {
            Log::channel('scraping')->info('Fetching matches for series', [
                'series_id' => $this->seriesId
            ]);

            $matches = $scraper->fetchMatches($this->seriesId, $this->seriesSlug);
            
            if (empty($matches)) {
                Log::channel('scraping')->warning('No matches found for series', [
                    'series_id' => $this->seriesId
                ]);
                return;
            }

            $processor->processMatches($matches, $this->seriesId, $this->sportId);

            // Dispatch match processing jobs with rate limiting
            foreach ($matches as $match) {
                if ($this->shouldProcessMatch($match)) {
                    $this->dispatchMatchJobs($match);
                }
            }

        } catch (Throwable $e) {
            $this->handleFailure($e);
        }
    }

    protected function shouldProcessMatch(array $match): bool
    {
        return !empty($match['match_id']) && 
               !empty($match['match_slug']) &&
               ($match['status'] ?? '') !== 'completed';
    }

    protected function dispatchMatchJobs(array $match): void
    {
        ProcessSquadJob::dispatch($match['match_id'], $match['match_slug'])
            ->onQueue('cricbuzz-squads')
            ->delay(now()->addSeconds(5)); // Rate limiting

        ProcessScorecardJob::dispatch($match['match_id'], $match['match_slug'])
            ->onQueue('cricbuzz-scorecards')
            ->delay(now()->addSeconds(10)); // Staggered processing
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('scraping')->error('Match processing failed', [
            'series_id' => $this->seriesId,
            'error' => $exception->getMessage()
        ]);
    }
}