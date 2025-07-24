<?php
namespace App\Jobs\Cricbuzz;

use App\Services\CricbuzzScraper;
use App\Services\CricketDataProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessScorecardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $matchId;
    public string $matchSlug;

    public function __construct(string $matchId, string $matchSlug)
    {
        $this->matchId = $matchId;
        $this->matchSlug = $matchSlug;
    }

    public function handle(CricbuzzScraper $scraper, CricketDataProcessor $processor): void
    {
        try {
            $scorecard = $scraper->fetchScorecard($this->matchId, $this->matchSlug);
            if (!empty($scorecard)) {
                $processor->processScorecard($scorecard, $this->matchId, sportId: 1);
            }
        } catch (\Exception $e) {
            logger()->error("ScorecardJob failed: {$e->getMessage()}");
        }
    }
}
