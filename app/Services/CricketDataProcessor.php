<?php

namespace App\Services;

use App\Models\Fixture;
use App\Models\Matches;
use App\Models\Score; 
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\MatchDetail;
class CricketDataProcessor
{
    public function processFixtures(array $seriesData, int $sportId = 1): void
    {
        $fixtures = [];
        Log::info("Processing " . count($seriesData) . " fixtures for sport ID: {$sportId}");

        foreach ($seriesData as $series) {
            try {
                // Extract season from date_range
                $season = null;
                if (preg_match('/(\d{4})/', $series['date_range'], $matches)) {
                    $season = $matches[1];
                }

                // Prepare metadata
                $metadata = [
                    'series_slug' => $series['series_slug'],
                    'url' => $series['url'],
                    'date_range' => $series['date_range'],
                ];

                $fixtures[] = [
                    'sport_id' => $sportId,
                    'external_id' => $series['Externalseries_id'],
                    'name' => $series['series_name'],
                    'country' => null,
                    'season' => $season,
                    'metadata' => json_encode($metadata),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            } catch (\Exception $e) {
                Log::warning("Error processing fixture " . ($series['series_name'] ?? 'unknown') . ": " . $e->getMessage());
            }
        }

        if (!empty($fixtures)) {
            Fixture::upsert(
                $fixtures,
                ['external_id'],
                ['name', 'country', 'season', 'metadata', 'updated_at']
            );
            Log::info("Successfully processed " . count($fixtures) . " fixtures.");
        } else {
            Log::info("No fixtures to process.");
        }
    }

public function processMatches(array $matchesData, string $seriesId, int $sportId = 1): void
{
    $matches = [];
    $fixture = Fixture::where('external_id', $seriesId)->first();
    $fixtureId = $fixture ? $fixture->id : null;

    foreach ($matchesData as $matchData) {
        try {
            $startTime = null;
            if (!empty($matchData['datetime_info'])) {
                $parts = explode('/', $matchData['datetime_info']);
                $gmtPart = trim($parts[0]);
                
                if (preg_match('/([A-Za-z]{3}) (\d{2}), [A-Za-z]{3}, (\d{1,2}:\d{2} [AP]M)/i', $gmtPart, $datetimeMatches)) {
                    $startTime = Carbon::createFromFormat(
                        'M d h:i A Y',
                        sprintf('%s %s %s %s', 
                            $datetimeMatches[1], 
                            $datetimeMatches[2], 
                            $datetimeMatches[3], 
                            date('Y')
                        ),
                        'GMT'
                    );
                }
            }

            $matches[] = [
                'external_match_id' => $matchData['match_id'] ?? null,
                'sport_id' => $sportId,
                'fixture_id' => $fixtureId,
                'home_team' => $matchData['team1'] ?? null,
                'away_team' => $matchData['team2'] ?? null,
                'status' => $matchData['match_status'] ?? 'unknown',
                'result' => $matchData['result'] ?? null,
                'start_time' => $startTime,
                'league' => null,
                'metadata' => [
                    'match_slug' => $matchData['match_slug'] ?? null,
                    'url' => $matchData['url'] ?? null,
                    'squad_url' => $matchData['squad_url'] ?? null,
                    'scorecard_url' => $matchData['scorecard_url'] ?? null,
                    'venue' => $matchData['venue'] ?? null,
                ],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        } catch (\Exception $e) {
            Log::error("Error processing match: " . $e->getMessage());
        }
    }

    if (!empty($matches)) {
        try {
            // Convert metadata to JSON strings
            $matches = array_map(function ($match) {
                $match['metadata'] = json_encode($match['metadata']);
                return $match;
            }, $matches);

            Matches::upsert(
                $matches,
                ['external_match_id'],
                [
                    'sport_id',
                    'fixture_id',
                    'home_team',
                    'away_team',
                    'status',
                    'result',
                    'start_time',
                    'league',
                    'metadata',
                    'updated_at'
                ]
            );
            Log::info("Successfully processed " . count($matches) . " matches");
        } catch (\Exception $e) {
            Log::error("Upsert failed: " . $e->getMessage());
            Log::debug("Failed matches data: ", $matches);
        }
    }
}

  public function processSquad(array $squads, int $externalMatchId, int $sportId = 1): void
{
    try {
        // Step 1: Resolve internal match ID from matches table
        $match = Matches::where('external_match_id', $externalMatchId)->first();

        if (!$match) {
            Log::warning("No internal match found for external match ID {$externalMatchId}");
            return;
        }

        // Step 2: Find or create MatchDetail by internal match ID
        $matchDetail = MatchDetail::firstOrNew([
            'match_id' => $match->id
        ]);

        // Step 3: Assign squad and additional info
        $matchDetail->squad = $squads;
        $matchDetail->additional_info = [
            'processed_at' => now()->toDateTimeString(),
            'sport_id' => $sportId
        ];

        // Step 4: Save to DB
        $matchDetail->save();

        Log::info("Squad data saved for match ID {$match->id} (external match ID: {$externalMatchId})");
    } catch (\Exception $e) {
        Log::error("Failed to process/save squad for external match ID {$externalMatchId}: " . $e->getMessage());
    }
}




public function processScorecard(array $scorecard, string $externalMatchId, int $sportId = 1): void
{
    $match = \App\Models\Matches::where('external_match_id', $externalMatchId)
        ->where('sport_id', $sportId)
        ->first();

    if (!$match) {
        Log::warning("Match not found in matches table for external_match_id: {$externalMatchId}");
        return;
    }

    try {
        Score::updateOrCreate(
            ['match_id' => $match->id],
            [
                'score_data' => json_encode($scorecard),
                'metadata' => json_encode([
                    'source' => 'Cricbuzz',
                    'scraped_at' => now(),
                ]),
            ]
        );

        Log::info("✅ Scorecard stored successfully for match ID {$match->id}");
    } catch (\Exception $e) {
        Log::error("❌ Failed to store scorecard for match ID {$match->id}: " . $e->getMessage());
    }
}

}
