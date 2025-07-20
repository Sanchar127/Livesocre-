<?php
namespace App\Jobs;

use App\Services\CricketDataProcessor;
use App\Services\CricbuzzScraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCricbuzzScrape implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CricbuzzScraper $scraper, CricketDataProcessor $processor): void
    {
        Log::info('Starting Cricbuzz data scrape job...');

        try {
            $series = $scraper->fetchSeriresOrTournament();
            Log::info("Fetched series list", ['count' => count($series)]);

            if (empty($series)) return;

            $processor->processFixtures($series, sportId: 1);

            foreach ($series as $seriesItem) {
                $seriesId = $seriesItem['Externalseries_id'];
                $seriesSlug = $seriesItem['series_slug'];
                $seriesName = $seriesItem['series_name'] ?? 'Unknown';

                if (!$seriesId || !$seriesSlug) continue;

                $matches = $scraper->fetchMatches($seriesId, $seriesSlug);
                if (!empty($matches)) {
                    $processor->processMatches($matches, $seriesId, sportId: 1);

                    $match = $matches[0];
                    $matchId = $match['match_id'];
                    $matchSlug = $match['match_slug'];
                    $matchTitle = $match['title'] ?? 'N/A';

                    if ($matchId && $matchSlug) {
                        try {
                            $squad = $scraper->fetchSquad($matchId, $matchSlug);
                            if (!empty($squad)) {
                                $processor->processSquad($squad, $matchId, sportId: 1);
                            }
                        } catch (\Exception $e) {
                            Log::error("Squad error: {$e->getMessage()}");
                        }

                        try {
                            $scorecard = $scraper->fetchScorecard($matchId, $matchSlug);
                            if (!empty($scorecard)) {
                                $processor->processScorecard($scorecard, $matchId, sportId: 1);
                            }
                        } catch (\Exception $e) {
                            Log::error("Scorecard error: {$e->getMessage()}");
                        }
                    }
                }
            }

            Log::info('Cricbuzz data scrape completed successfully.');
        } catch (\Exception $e) {
            Log::error("Cricbuzz scrape failed: " . $e->getMessage());
        }
    }
}
