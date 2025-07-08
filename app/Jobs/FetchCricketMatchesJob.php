<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use App\Events\CricketMatchesUpdated;

class FetchCricketMatchesJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $tries = 3;
    public $timeout = 30;

    public function handle()
    {
        try {
            Log::info('🎯 FetchCricketMatchesJob started.');

            $url = 'https://api.cricapi.com/v1/currentMatches?apikey=5b5c582d-bb03-4243-a539-028aa954ecf7';
            $response = Http::timeout(15)->get($url);

            if (!$response->ok()) {
                Log::error('❌ Cricket API fetch failed: ' . $response->body());
                return;
            }

            $matches = $response->json()['data'] ?? [];

            // Store full matches in cache for 5 minutes
            Cache::put('latest_cricket_matches', $matches, now()->addMinutes(5));

            // Fire lightweight event just notifying frontend to fetch new data
            event(new CricketMatchesUpdated([
                'message' => 'Matches updated',
                'count' => count($matches)
            ]));

            Log::info('✅ FetchCricketMatchesJob completed. Matches fetched: ' . count($matches));
        } catch (\Exception $e) {
            Log::error('🔥 FetchCricketMatchesJob failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
