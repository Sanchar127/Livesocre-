<?php

namespace App\Console\Commands; // 👈 this too must be present

use Illuminate\Console\Command; // ✅ This line is missing
use App\Jobs\ProcessCricbuzzScrape;

class ScrapeCricbuzzMatches extends Command
{
    protected $signature = 'scrape:cricbuzz';
    protected $description = 'Queue Cricbuzz scraping job';

    public function handle()
    {
        $this->info("Dispatching Cricbuzz scraping job...");
        ProcessCricbuzzScrape::dispatch();
        $this->info("Job dispatched successfully.");
    }
}
