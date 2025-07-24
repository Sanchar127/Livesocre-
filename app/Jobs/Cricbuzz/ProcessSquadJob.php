<?php
namespace App\Jobs\Cricbuzz;

use App\Services\CricbuzzScraper;
use App\Services\CricketDataProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSquadJob implements ShouldQueue
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
            $squad = $scraper->fetchSquad($this->matchId, $this->matchSlug);
            if (!empty($squad)) {
                $processor->processSquad($squad, $this->matchId, sportId: 1);
            }
        } catch (\Exception $e) {
            logger()->error("SquadJob failed: {$e->getMessage()}");
        }
    }
}
