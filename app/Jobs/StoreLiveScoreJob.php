<?php

namespace App\Jobs;

use App\Models\Fixture;
use App\Models\Sport;
use App\Models\Matches as MatchModel;
use App\Models\Score;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use SimpleXMLElement;

class StoreLiveScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $apiKey = '0ddaa180b8bc4de749a508ddbacda0d5';
    protected string $baseUrl = 'https://www.goalserve.com/getfeed';
    protected int $cacheMinutes = 1440;
    protected int $maxRetries = 3;
    protected int $retryDelay = 60;
    protected Sport $sport;

    public function __construct(Sport $sport)
    {
        $this->sport = $sport;
    }

    public function handle(): void
{
    try {
        Log::info('Starting live scores processing job', ['sport' => $this->sport->name]);

        if ($this->sport->slug === 'cricket') {
            // For cricket, we have a different processing flow
            // Step 1: Fetch league mappings (tournaments in cricket)
            $leagueData = $this->fetchCricketLeagueMappings();
            $leagues = $leagueData['leagues'];

            // Step 2: Process schedule matches
            $scheduleData = $this->fetchCricketScheduleMatches(); // ✅ matches the new method

            $this->processCricketScheduleMatches($scheduleData['matches']);

            // Step 3: Process LIVE scores from live API
            $liveScoresData = $this->fetchCricketLiveScores();
            $this->processCricketLiveScores($liveScoresData['matches']);

            Log::info('Cricket scores processing completed', [
                'sport' => $this->sport->name,
                'schedule_matches_processed' => count($scheduleData['matches']),
                'live_scores_processed' => count($liveScoresData['matches'])
            ]);
        } else {
            // Original football processing
            // Step 1: Fetch league mappings
            $leagueData = $this->fetchLeagueMappings();
            $leagues = $leagueData['leagues'];

            // Step 2: Process upcoming matches from d7 API
            $matchData = $this->fetchMatchData();
            $this->processD7Matches($matchData['matches'], $leagues);

            // Step 3: Process LIVE scores from live API
            $liveScoresData = $this->fetchLiveScores();
            $this->processLiveScores($liveScoresData['matches']);

            Log::info('Live scores processing completed', [
                'sport' => $this->sport->name,
                'd7_matches_processed' => count($matchData['matches']),
                'live_scores_processed' => count($liveScoresData['matches'])
            ]);
        }

    } catch (\Throwable $e) {
        Log::error('Job processing failed', [
            'error' => $e->getMessage(),
            'attempt' => $this->attempts(),
            'sport' => $this->sport->name,
            'trace' => $e->getTraceAsString(),
        ]);

        if ($this->attempts() < $this->maxRetries) {
            $this->release($this->retryDelay);
        }
    }
}

    protected function fetchLeagueMappings(): array
{
   

    // Original football implementation
    $cacheKey = "goalserve_league_mappings_{$this->sport->slug}";
    
    return Cache::remember($cacheKey, $this->cacheMinutes, function () {
        $url = "{$this->baseUrl}/{$this->apiKey}/{$this->sport->slug}fixtures/data/mapping";
        Log::debug('Fetching league mappings', ['url' => $url]);

        $response = Http::timeout(30)->retry(3, 1000)->get($url);

        if ($response->failed()) {
            Log::warning('League mappings API failed', ['status' => $response->status()]);
            return ['leagues' => [], 'sport_id' => $this->sport->id];
        }

        $xml = $this->parseXml($response->body());
        if (!$xml) {
            Log::warning('Invalid league mappings XML');
            return ['leagues' => [], 'sport_id' => $this->sport->id];
        }

        $leagues = [];
        $mappings = $xml->mapping ?? ($xml->fixtures->mapping ?? []);

        foreach ($mappings as $mapping) {
            $this->processMappingNode($mapping, $leagues);
        }

        return ['leagues' => $leagues, 'sport_id' => $this->sport->id];
    });
}

protected function fetchCricketLeagueMappings(): array
{
    $cacheKey = "goalserve_cricket_league_mappings";
    
    return Cache::remember($cacheKey, $this->cacheMinutes, function () {
        $url = "https://www.goalserve.com/getfeed/0ddaa180b8bc4de749a508ddbacda0d5/cricket/schedule";
        Log::debug('Fetching cricket schedule for league mappings', ['url' => $url]);

        $response = Http::timeout(30)->retry(3, 1000)->get($url);

        if ($response->failed()) {
            Log::warning('Cricket schedule API failed', ['status' => $response->status()]);
            return ['leagues' => [], 'sport_id' => $this->sport->id];
        }

        $xml = $this->parseXml($response->body());
        if (!$xml) {
            Log::warning('Invalid cricket schedule XML');
            return ['leagues' => [], 'sport_id' => $this->sport->id];
        }

        $leagues = [];

        // Process tournaments as leagues
        if (isset($xml->tournament)) {
            foreach ($xml->tournament as $tournament) {
                $attrs = $tournament->attributes();
                $id = (string)$attrs->id;
                $name = (string)$attrs->name;
                
                if ($id && $name) {
                    $leagues[$id] = $name;

                    Fixture::updateOrCreate(
                        ['external_id' => $id],
                        [
                            'name' => $name,
                            'country' => $this->extractCountryFromTournament($name),
                            'season' => $this->extractSeasonFromTournament($name),
                            'sport_id' => $this->sport->id,
                            'league_external_id' => $id,
                        ]
                    );
                }
            }
        }

        return ['leagues' => $leagues, 'sport_id' => $this->sport->id];
    });
}

protected function fetchMatchData(): array
{
    if ($this->sport->slug === 'cricket') {
        return $this->fetchCricketSchedule();
    }

    // Original football implementation
    try {
        $url = "{$this->baseUrl}/{$this->apiKey}/{$this->sport->slug}/d7";
        Log::debug('Fetching match data', ['url' => $url]);

        $response = Http::timeout(30)->retry(3, 1000)->get($url);

        if ($response->failed()) {
            Log::warning('Match data API failed', ['status' => $response->status()]);
            return ['sport' => $this->sport->name, 'matches' => []];
        }

        $xml = $this->parseXml($response->body());
        if (!$xml) {
            Log::warning('Invalid match data XML');
            return ['sport' => $this->sport->name, 'matches' => []];
        }

        $matches = [];

        if (isset($xml->category)) {
            foreach ($xml->category as $category) {
                $attrs = $category->attributes();
                $categoryName = (string)$attrs->name;
                $categoryId = (string)$attrs->id;

                if (isset($category->matches->match)) {
                    foreach ($category->matches->match as $match) {
                        $matchData = $this->parseMatchElement($match);
                        $matchData['category'] = $categoryName;
                        $matchData['league_id'] = $categoryId;
                        $matches[] = $matchData;
                    }
                }
            }
        }

        return ['sport' => $this->sport->name, 'matches' => $matches];
    } catch (\Exception $e) {
        Log::error('Match data fetch exception', ['error' => $e->getMessage()]);
        return ['sport' => $this->sport->name, 'matches' => []];
    }
}

protected function fetchCricketScheduleMatches(): array
{
    $url = "https://www.goalserve.com/getfeed/0ddaa180b8bc4de749a508ddbacda0d5/cricket/schedule";
    Log::debug('Fetching cricket schedule', ['url' => $url]);

    $response = Http::timeout(30)->retry(3, 1000)->get($url);

    if ($response->failed()) {
        Log::warning('Cricket schedule API failed', ['status' => $response->status()]);
        return ['matches' => []];
    }

    $xml = $this->parseXml($response->body());
    if (!$xml) {
        Log::warning('Invalid cricket schedule XML');
        return ['matches' => []];
    }

    $matches = [];

    foreach ($xml->category as $category) {
        $categoryAttrs = $category->attributes();
        $tournamentId = (string)($categoryAttrs->id ?? null);
        $tournamentName = (string)($categoryAttrs->name ?? 'Unknown Tournament');

        foreach ($category->match as $match) {
            $matches[] = [
                'match' => $match,
                'tournament_id' => $tournamentId,
                'tournament_name' => $tournamentName,
            ];
        }
    }

    return ['matches' => $matches];
}

protected function parseCricketScheduleMatch(SimpleXMLElement $match): array
{
    $data = [
        '@attributes' => [],
        'home_team' => ['@attributes' => []],
        'away_team' => ['@attributes' => []],
        'match_status' => [],
    ];

    // Parse match attributes
    foreach ($match->attributes() as $key => $value) {
        $data['@attributes'][$key] = (string)$value;
    }

    // Parse home team
    if (isset($match->home)) {
        foreach ($match->home->attributes() as $key => $value) {
            $data['home_team']['@attributes'][$key] = (string)$value;
        }
    }

    // Parse away team
    if (isset($match->away)) {
        foreach ($match->away->attributes() as $key => $value) {
            $data['away_team']['@attributes'][$key] = (string)$value;
        }
    }

    // Parse match status
    if (isset($match->match_status)) {
        foreach ($match->match_status->attributes() as $key => $value) {
            $data['match_status'][$key] = (string)$value;
        }
    }

    // Parse date/time
    if (isset($match->date)) {
        $data['date'] = (string)$match->date;
    }
    if (isset($match->time)) {
        $data['time'] = (string)$match->time;
    }

    return $data;
}

protected function processCricketScheduleMatches(array $matches): void
{
    foreach ($matches as $matchData) {
        try {
            $match = $matchData['match'];
            $matchAttrs = $match->attributes();

            $matchId = (string)($matchAttrs->id ?? null);
            if (!$matchId) {
                Log::debug('Skipping cricket match with empty ID', ['match_data' => $matchData]);
                continue;
            }

            $tournamentId = $matchData['tournament_id'];
            $tournamentName = $matchData['tournament_name'];

            if (!$tournamentId) {
                Log::warning('Skipping match due to missing tournament ID', ['match_id' => $matchId]);
                continue;
            }

            Log::debug('Filling cricket fixture data');

            // Create/update fixture
            $fixture = Fixture::updateOrCreate(
                ['external_id' => $tournamentId],
                [
                    'name' => $tournamentName,
                    'country' => $this->extractCountryFromTournament($tournamentName),
                    'season' => $this->extractSeasonFromTournament($tournamentName),
                    'sport_id' => $this->sport->id,
                    'league_external_id' => $tournamentId,
                ]
            );

            // Extract teams
            $homeTeam = (string)($match->localteam['name'] ?? 'Unknown');
            $awayTeam = (string)($match->visitorteam['name'] ?? 'Unknown');
            $status = $this->normalizeCricketStatus('scheduled');

            $dateTime = $this->parseCricketMatchTime(
                (string)($matchAttrs->date ?? null),
                (string)($matchAttrs->time ?? null)
            );

            $metadata = [
                'match_type' => (string)($matchAttrs->type ?? null),
                'match_num' => (string)($matchAttrs->match_num ?? null),
                'tournament_id' => $tournamentId,
            ];

            MatchModel::updateOrCreate(
                ['external_match_id' => $matchId],
                [
                    'sport_id' => $this->sport->id,
                    'fixture_id' => $fixture->id,
                    'home_team' => $homeTeam,
                    'away_team' => $awayTeam,
                    'status' => $status,
                    'start_time' => $dateTime,
                    'league' => $tournamentName,
                    'metadata' => $metadata,
                ]
            );

            Log::debug('Cricket match processed', [
                'match_id' => $matchId,
                'home' => $homeTeam,
                'away' => $awayTeam,
                'tournament' => $tournamentName
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process cricket match', [
                'match_id' => $matchId ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}


protected function normalizeCricketStatus(string $status): string
{
    $status = strtolower(trim($status));
    
    return match ($status) {
        'notstarted', 'ns' => 'scheduled',
        'inprogress', 'live', 'started' => 'live',
        'completed', 'finished', 'result' => 'finished',
        'abandoned', 'cancelled' => 'cancelled',
        'delayed' => 'delayed',
        default => 'unknown',
    };
}

protected function extractCountryFromTournament(string $tournamentName): string
{
    // Try to extract country from tournament name
    if (preg_match('/\((.*?)\)/', $tournamentName, $matches)) {
        return $matches[1];
    }
    
    // Fallback to common cricket nations
    $countries = ['England', 'Australia', 'India', 'Pakistan', 'South Africa', 
                 'New Zealand', 'West Indies', 'Sri Lanka', 'Bangladesh'];
    
    foreach ($countries as $country) {
        if (stripos($tournamentName, $country) !== false) {
            return $country;
        }
    }
    
    return 'International';
}

protected function extractSeasonFromTournament(string $tournamentName): string
{
    // Try to extract year from tournament name
    if (preg_match('/\b(20\d{2})\b/', $tournamentName, $matches)) {
        $year = $matches[1];
        return "$year/" . ($year + 1);
    }
    
    return date('Y') . '/' . (date('Y') + 1);
}

protected function processD7Matches(array $matches, array $leagues): void
{
    foreach ($matches as $matchData) {
        try {
            $matchId = (string)($matchData['@attributes']['id'] ?? null);
            if (!$matchId) continue;

            $attributes = $matchData['@attributes'];
            $homeTeam = $matchData['localteam']['@attributes'] ?? [];
            $awayTeam = $matchData['visitorteam']['@attributes'] ?? [];
            $leagueId = $matchData['league_id'] ?? null;
            $fixId = $attributes['fix_id'] ?? $leagueId;

            if (!$leagueId) {
                Log::warning('Skipping match with missing league_id', ['match_id' => $matchId]);
                continue;
            }

            // For cricket, use tournament name as league name
            $leagueName = $this->sport->slug === 'cricket' 
                ? ($matchData['category'] ?? 'Unknown Tournament')
                : ($leagues[$leagueId] ?? $matchData['category'] ?? 'Unknown League');

            // Create/update fixture
            $fixture = Fixture::firstOrCreate(
                ['external_id' => $fixId ?: $leagueId],
                [
                    'sport_id' => $this->sport->id,
                    'league_external_id' => $leagueId,
                    'name' => $leagueName,
                    'country' => $this->extractCountryFromCategory($matchData['category'] ?? ''),
                    'season' => $this->extractSeasonFromDate($attributes['date'] ?? null),
                ]
            );

            // Cricket-specific data preparation
            $metadata = [
                'venue' => $attributes['venue'] ?? null,
                'commentary_available' => $attributes['commentary_available'] ?? null,
                'static_id' => $attributes['static_id'] ?? null,
            ];

            if ($this->sport->slug === 'cricket') {
                $metadata['match_type'] = $attributes['matchtype'] ?? null;
                $metadata['format'] = $attributes['format'] ?? null;
            }

            // Create/update match (metadata only)
            MatchModel::updateOrCreate(
                ['external_match_id' => $matchId],
                [
                    'sport_id' => $this->sport->id,
                    'fixture_id' => $fixture->id,
                    'home_team' => $homeTeam['name'] ?? 'Unknown',
                    'away_team' => $awayTeam['name'] ?? 'Unknown',
                    'status' => $this->normalizeStatus($attributes['status'] ?? 'unknown'),
                    'start_time' => $this->parseMatchTime(
                        $attributes['date'] ?? null,
                        $attributes['formatted_date'] ?? null,
                        $attributes['time'] ?? null
                    ),
                    'league' => $leagueName,
                    'metadata' => $metadata,
                ]
            );

            Log::debug('Match metadata processed', ['match_id' => $matchId]);

        } catch (\Exception $e) {
            Log::error('Failed to process match', [
                'match_data' => $matchData,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
   

protected function fetchCricketLiveScores(): array
{
    try {
        $url = "https://www.goalserve.com/getfeed/0ddaa180b8bc4de749a508ddbacda0d5/cricket/livescore?json=1";
        $response = Http::timeout(30)->retry(3, 1000)->get($url);

        $rawResponse = $response->body();
        Log::debug('Raw Cricket Live API JSON response sample', ['sample' => substr($rawResponse, 0, 500)]);

        if ($response->failed()) {
            Log::warning('Cricket API request failed', [
                'status' => $response->status(),
                'body' => $rawResponse
            ]);
            return ['sport' => $this->sport->name, 'matches' => []];
        }

        $matchesData = json_decode($rawResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to decode cricket live scores JSON', [
                'error' => json_last_error_msg(),
                'raw' => $rawResponse
            ]);
            return ['sport' => $this->sport->name, 'matches' => []];
        }

        $matches = $this->parseCricketLiveMatchesFromJson($matchesData);

        Log::debug('Parsed live cricket matches count', ['count' => count($matches)]);

        return ['sport' => $this->sport->name, 'matches' => $matches];
    } catch (\Exception $e) {
        Log::error('Cricket live scores fetch exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return ['sport' => $this->sport->name, 'matches' => []];
    }
}


    protected function processLiveScores(array $liveMatches): void
{
    foreach ($liveMatches as $matchData) {
        try {
            $matchId = (string)($matchData['@attributes']['id'] ?? null);
            if (!$matchId) {
                Log::debug('Skipping match with empty ID', ['match_data' => $matchData]);
                continue;
            }

            // 1. Try to find match by external ID first
            $match = MatchModel::where('external_match_id', $matchId)->first();

            // 2. If not found, try to find by team names and approximate time
            if (!$match) {
                $homeTeam = $matchData['localteam']['@attributes']['name'] ?? null;
                $awayTeam = $matchData['visitorteam']['@attributes']['name'] ?? null;
                $matchTime = $this->parseMatchTime(
                    $matchData['@attributes']['date'] ?? null,
                    $matchData['@attributes']['formatted_date'] ?? null,
                    $matchData['@attributes']['time'] ?? null
                );

                if ($homeTeam && $awayTeam && $matchTime) {
                    $match = MatchModel::where('home_team', $homeTeam)
                        ->where('away_team', $awayTeam)
                        ->whereBetween('start_time', [
                            $matchTime->clone()->subHours(6),
                            $matchTime->clone()->addHours(6)
                        ])
                        ->first();

                    // If found, update with the live match ID
                    if ($match) {
                        $match->update(['external_match_id' => $matchId]);
                        Log::debug('Matched live score to existing match by teams+time', [
                            'match_id' => $matchId,
                            'db_id' => $match->id
                        ]);
                    }
                }
            }

            // 3. If still not found, create a minimal match record
            if (!$match) {
                $leagueId = $matchData['league_id'] ?? null;
                $fixture = $leagueId ? Fixture::firstOrCreate(
                    ['external_id' => $leagueId],
                    [
                        'sport_id' => $this->sport->id,
                        'league_external_id' => $leagueId,
                        'name' => $matchData['category'] ?? 'Unknown League',
                        'country' => $this->extractCountryFromCategory($matchData['category'] ?? ''),
                        'season' => $this->extractSeasonFromDate($matchData['@attributes']['date'] ?? null),
                    ]
                ) : null;

                $match = MatchModel::create([
                    'external_match_id' => $matchId,
                    'sport_id' => $this->sport->id,
                    'fixture_id' => $fixture?->id,
                    'home_team' => $matchData['localteam']['@attributes']['name'] ?? 'Unknown',
                    'away_team' => $matchData['visitorteam']['@attributes']['name'] ?? 'Unknown',
                    'status' => $this->normalizeStatus($matchData['@attributes']['status'] ?? 'unknown'),
                    'start_time' => $matchTime ?? now(),
                    'league' => $matchData['category'] ?? 'Unknown League',
                    'metadata' => [
                        'created_from' => 'live_score_fallback',
                        'original_data' => $matchData
                    ]
                ]);

                Log::debug('Created new match record for live score', [
                    'match_id' => $matchId,
                    'db_id' => $match->id
                ]);
            }

            // Process the score data
            $scoreData = [
                'home_score' => (int)($matchData['localteam']['@attributes']['goals'] ?? 0),
                'away_score' => (int)($matchData['visitorteam']['@attributes']['goals'] ?? 0),
                'half_time_score' => $this->sanitizeScore($matchData['ht']['score'] ?? null),
                'full_time_score' => $this->sanitizeScore($matchData['ft']['score'] ?? null),
                'extra_time_score' => $this->sanitizeScore($matchData['et']['score'] ?? null),
                'match_status' => $this->normalizeStatus($matchData['@attributes']['status'] ?? 'unknown'),
                'goal_events' => $this->extractEventsByType($matchData['events'] ?? [], 'goal'),
                'red_card_events' => $this->extractEventsByType($matchData['events'] ?? [], 'redcard'),
                'yellow_card_events' => $this->extractEventsByType($matchData['events'] ?? [], 'yellowcard'),
                'substitution_events' => $this->extractEventsByType($matchData['events'] ?? [], 'substitution'),
            ];

            Score::updateOrCreate(
                ['match_id' => $match->id],
                [
                    'score_data' => $scoreData,
                    'updated_at' => now()
                ]
            );

            // Update match status from live data
            $match->update([
                'status' => $this->normalizeStatus($matchData['@attributes']['status'] ?? 'unknown')
            ]);

            Log::info('Live score processed', [
                'match_id' => $matchId,
                'home' => $matchData['localteam']['@attributes']['name'] ?? 'Unknown',
                'away' => $matchData['visitorteam']['@attributes']['name'] ?? 'Unknown',
                'score' => $scoreData['home_score'] . '-' . $scoreData['away_score'],
                'status' => $scoreData['match_status']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process live score', [
                'match_id' => $matchId ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}


protected function parseCricketLiveMatchesFromJson(array $jsonData): array
{
    $matches = [];

    if (empty($jsonData['scores']['category']) || !is_array($jsonData['scores']['category'])) {
        return $matches;
    }

    foreach ($jsonData['scores']['category'] as $category) {
        $categoryName = $category['name'] ?? null;
        $categoryId = $category['id'] ?? null;

        if (!isset($category['match'])) {
            continue;
        }

        $categoryMatches = $category['match'];

        // Check if $categoryMatches is an array of matches or a single match object
        if (isset($categoryMatches[0])) {
            // It's an array of matches
            foreach ($categoryMatches as $match) {
                // Add category info to match if you want
                $match['category_name'] = $categoryName;
                $match['category_id'] = $categoryId;
                $matches[] = $match;
            }
        } else {
            // Single match object
            $categoryMatches['category_name'] = $categoryName;
            $categoryMatches['category_id'] = $categoryId;
            $matches[] = $categoryMatches;
        }
    }

    return $matches;
}

protected function fetchLiveScores(): array
    {
        try {
            $url = "{$this->baseUrl}/{$this->apiKey}/{$this->sport->slug}new/live";
            Log::debug('Fetching live scores', ['url' => $url]);

            $response = Http::timeout(30)->retry(3, 1000)->get($url);

            if ($response->failed()) {
                Log::warning('Live scores API failed', [
                    'status' => $response->status(),
                ]);
                return ['sport' => $this->sport->name, 'matches' => []];
            }

            $xml = $this->parseXml($response->body());
            if (!$xml) {
                Log::warning('Invalid live scores XML');
                return ['sport' => $this->sport->name, 'matches' => []];
            }

            $matches = [];

            if (isset($xml->category)) {
                foreach ($xml->category as $category) {
                    $attrs = $category->attributes();
                    $categoryName = (string)$attrs->name;
                    $categoryId = (string)$attrs->id;

                    if (isset($category->matches->match)) {
                        foreach ($category->matches->match as $match) {
                            $matchData = $this->parseMatchElement($match);
                            $matchData['category'] = $categoryName;
                            $matchData['league_id'] = $categoryId;
                            $matches[] = $matchData;
                        }
                    }
                }
            }

            return [
                'sport' => $this->sport->name,
                'matches' => $matches,
            ];
        } catch (\Exception $e) {
            Log::error('Live scores fetch exception', [
                'error' => $e->getMessage(),
            ]);
            return ['sport' => $this->sport->name, 'matches' => []];
        }
    }


protected function processCricketLiveScores(array $liveMatches): void
{
    Log::debug('Processing live cricket scores... live match count ', ['match_count' => count($liveMatches)]);

    foreach ($liveMatches as $matchData) {
        // Log full match data
        Log::debug('Raw match data', ['match_data' => json_encode($matchData, JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR)]);

        // Log specific fields for score construction
        Log::debug('Raw match data for score construction', [
            'match_id' => $matchData['id'] ?? null,
            'localteam' => $matchData['localteam'] ?? null,
            'visitorteam' => $matchData['visitorteam'] ?? null,
            'inning' => $matchData['inning'] ?? null,
            'status' => $matchData['status'] ?? null,
            'wickets' => $matchData['wickets'] ?? null,
        ]);

        try {
            $matchId = (string)($matchData['id'] ?? null);
            if (!$matchId) {
                Log::debug('Skipping cricket match with empty ID', ['match_data' => $matchData]);
                continue;
            }

            $match = MatchModel::where('external_match_id', $matchId)->first();
            if (!$match) {
                Log::warning("Match with external_match_id {$matchId} not found, skipping update.");
                continue;
            }

            // Normalize status and parse time
            $status = $this->normalizeCricketStatus($matchData['status'] ?? 'unknown');
            $startTime = $this->parseCricketMatchTime(
                $matchData['date'] ?? null,
                $matchData['time'] ?? null
            ) ?? now();

            $match->update([
                'status' => $status,
                'start_time' => $startTime,
            ]);

            // Build score data from JSON structure
            $scoreData = [
                'home_team' => $matchData['localteam'] ?? [],
                'away_team' => $matchData['visitorteam'] ?? [],
                'current_innings' => $this->getCurrentInnings($matchData['inning'] ?? []), // Custom method to determine current innings
                'innings' => $this->formatInnings($matchData['inning'] ?? []), // Map API 'inning' to 'innings'
                'batsmen' => $this->formatBatsmen($matchData['inning'] ?? []), // Extract batsmen from innings
                'bowlers' => $this->formatBowlers($matchData['inning'] ?? []), // Extract bowlers from innings
                'match_status' => $status,
                'last_wicket' => $this->getLastWicket($matchData['wickets'] ?? []), // Extract last wicket
                'required_run_rate' => $matchData['required_run_rate'] ?? null, // Not present in API, keep as null
                'current_run_rate' => $matchData['current_run_rate'] ?? null, // Not present in API, keep as null
            ];

            // Validate score data
            if (empty(array_filter($scoreData, fn($value) => !is_null($value) && $value !== []))) {
                Log::warning('Skipping score update due to empty score data', [
                    'match_id' => $matchId,
                    'score_data' => $scoreData,
                ]);
                continue;
            }

            // Log score data before saving
            Log::debug('Score data before saving', [
                'match_id' => $matchId,
                'score_data' => $scoreData,
            ]);

            Score::updateOrCreate(
                ['match_id' => $match->id],
                [
                    'score_data' => $scoreData,
                    'updated_at' => now(),
                ]
            );

            Log::info('Cricket live score updated', [
                'match_id' => $matchId,
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process cricket live score', [
                'match_id' => $matchId ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

// Helper methods for score data construction
protected function getCurrentInnings(array $innings): ?string
{
    if (empty($innings)) {
        return null;
    }
    // Assume the last inning is the current one if the match is ongoing
    $lastInning = end($innings);
    return $lastInning['inningnum'] ?? null;
}

protected function formatInnings(array $innings): array
{
    $formatted = [];
    foreach ($innings as $inning) {
        $formatted[] = [
            'inning_number' => $inning['inningnum'] ?? null,
            'team' => $inning['team'] ?? null,
            'total' => $inning['total']['tot'] ?? null,
            'wickets' => $inning['total']['wickets'] ?? null,
            'overs' => $inning['total']['tot'] ? preg_replace('/.*\((.*)\)/', '$1', $inning['total']['tot']) : null,
            'run_rate' => $inning['total']['rr'] ?? null,
        ];
    }
    return $formatted;
}

protected function formatBatsmen(array $innings): array
{
    $batsmen = [];
    foreach ($innings as $inning) {
        if (isset($inning['batsmanstats']['player'])) {
            foreach ($inning['batsmanstats']['player'] as $player) {
                $batsmen[] = [
                    'name' => $player['batsman'] ?? null,
                    'runs' => $player['r'] ?? null,
                    'balls' => $player['b'] ?? null,
                    'fours' => $player['s4'] ?? null,
                    'sixes' => $player['s6'] ?? null,
                    'strike_rate' => $player['sr'] ?? null,
                    'status' => $player['status'] ?? null,
                    'inning_number' => $inning['inningnum'] ?? null,
                ];
            }
        }
    }
    return $batsmen;
}

protected function formatBowlers(array $innings): array
{
    $bowlers = [];
    foreach ($innings as $inning) {
        if (isset($inning['bowlers']['player'])) {
            foreach ($inning['bowlers']['player'] as $player) {
                $bowlers[] = [
                    'name' => $player['bowler'] ?? null,
                    'overs' => $player['o'] ?? null,
                    'runs' => $player['r'] ?? null,
                    'wickets' => $player['w'] ?? null,
                    'economy_rate' => $player['er'] ?? null,
                    'maidens' => $player['m'] ?? null,
                    'wides' => $player['wd'] ?? null,
                    'no_balls' => $player['nb'] ?? null,
                    'inning_number' => $inning['inningnum'] ?? null,
                ];
            }
        }
    }
    return $bowlers;
}

protected function getLastWicket(array $wickets): ?array
{
    if (empty($wickets['wicket'])) {
        return null;
    }
    // Get the last wicket (highest ID or latest overs)
    $lastWicket = end($wickets['wicket']);
    return [
        'player' => $lastWicket['player'] ?? null,
        'overs' => $lastWicket['overs'] ?? null,
        'runs' => $lastWicket['runs'] ?? null,
        'inning' => $lastWicket['inning'] ?? null,
        'description' => $lastWicket['post'] ?? null,
    ];
}

protected function parseCricketMatchTime(?string $date, ?string $time): ?Carbon
{
    try {
        if (!$date || !$time) {
            return null;
        }
        return Carbon::createFromFormat('d.m.Y H:i', "{$date} {$time}");
    } catch (\Exception $e) {
        Log::warning('Failed to parse cricket match time', [
            'date' => $date,
            'time' => $time,
            'error' => $e->getMessage()
        ]);
        return null;
    }
}
    protected function parseLiveMatchElement(SimpleXMLElement $match): array
    {
        $data = [
            '@attributes' => [],
            'localteam' => ['@attributes' => []],
            'visitorteam' => ['@attributes' => []],
            'events' => [],
            'ht' => ['score' => null],
            'ft' => ['score' => null],
            'et' => ['score' => null],
        ];

        // Only parse attributes needed for live scores
        foreach ($match->attributes() as $key => $value) {
            if (in_array($key, ['id', 'status', 'timer'])) {
                $data['@attributes'][$key] = (string)$value;
            }
        }

        if (isset($match->localteam)) {
            foreach ($match->localteam->attributes() as $key => $value) {
                if (in_array($key, ['name', 'goals'])) {
                    $data['localteam']['@attributes'][$key] = (string)$value;
                }
            }
        }

        if (isset($match->visitorteam)) {
            foreach ($match->visitorteam->attributes() as $key => $value) {
                if (in_array($key, ['name', 'goals'])) {
                    $data['visitorteam']['@attributes'][$key] = (string)$value;
                }
            }
        }

        // Only parse live events
        if (isset($match->events->event)) {
            foreach ($match->events->event as $event) {
                $eventData = [];
                foreach ($event->attributes() as $key => $value) {
                    $eventData[$key] = (string)$value;
                }
                $data['events'][] = $eventData;
            }
        }

        // Only parse score periods that exist
        foreach (['ht', 'ft', 'et'] as $period) {
            if (isset($match->$period) && isset($match->$period['score'])) {
                $data[$period]['score'] = (string)$match->$period['score'];
            }
        }

        return $data;
    }

    protected function isLiveMatch(array $matchData): bool
    {
        $status = strtolower($matchData['@attributes']['status'] ?? '');
        $timer = $matchData['@attributes']['timer'] ?? null;
        
        // Consider a match live if:
        // 1. Status indicates live play
        // 2. Or has a timer value (like "45:22")
        // 3. Or is in halftime
        return in_array($status, ['1st half', '2nd half', 'live', 'halftime']) || 
               ($timer && preg_match('/\d+:\d+/', $timer));
    }
    
    protected function processMappingNode(SimpleXMLElement $mapping, array &$leagues): void
    {
        $attrs = $mapping->attributes();
        if ($attrs) {
            $id = (string)$attrs->id;
            $name = (string)$attrs->name;
            $country = (string)$attrs->country;
            $season = (string)$attrs->season;

            if ($id && $name) {
                $leagues[$id] = $name;

                Fixture::updateOrCreate(
                    ['external_id' => $id],
                    [
                        'name' => $name,
                        'country' => $country,
                        'season' => $season,
                        'sport_id' => $this->sport->id,
                        'league_external_id' => $id,
                    ]
                );
            }
        }
    }

    protected function parseMatchElement(SimpleXMLElement $match): array
    {
        $data = [
            '@attributes' => [],
            'localteam' => ['@attributes' => []],
            'visitorteam' => ['@attributes' => []],
            'events' => [],
            'ht' => ['score' => null],
            'ft' => ['score' => null],
            'et' => ['score' => null],
        ];

        foreach ($match->attributes() as $key => $value) {
            $data['@attributes'][$key] = (string)$value;
        }

        if (isset($match->localteam)) {
            foreach ($match->localteam->attributes() as $key => $value) {
                $data['localteam']['@attributes'][$key] = (string)$value;
            }
        }

        if (isset($match->visitorteam)) {
            foreach ($match->visitorteam->attributes() as $key => $value) {
                $data['visitorteam']['@attributes'][$key] = (string)$value;
            }
        }

        if (isset($match->events->event)) {
            foreach ($match->events->event as $event) {
                $eventData = [];
                foreach ($event->attributes() as $key => $value) {
                    $eventData[$key] = (string)$value;
                }
                $data['events'][] = $eventData;
            }
        }

        foreach (['ht', 'ft', 'et'] as $period) {
            if (isset($match->$period) && isset($match->$period['score'])) {
                $data[$period]['score'] = (string)$match->$period['score'];
            }
        }

        if ($this->sport->slug === 'cricket') {
            $data = $this->parseCricketMatch($match, $data);
        }

        return $data;
    }

    protected function parseCricketMatch(SimpleXMLElement $match, array $data): array
    {
        $data['innings'] = [];
        if (isset($match->innings)) {
            foreach ($match->innings as $inning) {
                $inningData = [
                    'team' => (string)$inning->attributes()->team,
                    'runs' => (string)$inning->attributes()->runs,
                    'wickets' => (string)$inning->attributes()->wickets,
                    'overs' => (string)$inning->attributes()->overs,
                ];
                $data['innings'][] = $inningData;
            }
        }
        return $data;
    }
    protected function parseLiveStats(?string $liveStats): ?array
    {
        if (!$liveStats) {
            return null;
        }

        try {
            $parsed = json_decode($liveStats, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to parse live stats JSON', [
                    'live_stats' => $liveStats,
                    'error' => json_last_error_msg(),
                ]);
                return null;
            }
            return $parsed;
        } catch (\Exception $e) {
            Log::error('Error parsing live stats', [
                'live_stats' => $liveStats,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function prepareCricketScoreData(array $matchData): array
    {
        return [
            'innings' => $matchData['innings'] ?? [],
            'match_status' => $this->normalizeStatus($matchData['@attributes']['status'] ?? 'unknown'),
            'events' => $matchData['events'] ?? [],
        ];
    }

    protected function extractCountryFromCategory(string $category): string
    {
        $parts = explode(':', $category);
        return trim($parts[0] ?? 'Unknown');
    }

    protected function extractSeasonFromDate(?string $date): string
    {
        if (!$date) {
            return date('Y');
        }
        try {
            $year = Carbon::parse($date)->year;
            return "$year/" . ($year + 1);
        } catch (\Exception $e) {
            return date('Y');
        }
    }

    protected function parseXml(string $xmlString): ?SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);
        if ($xml === false) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                Log::error('XML parse error', [
                    'message' => trim($error->message),
                ]);
            }
            libxml_clear_errors();
            return null;
        }
        return $xml;
    }

    protected function parseMatchTime(?string $date, ?string $formattedDate, ?string $time): ?Carbon
    {
        if (!$time) {
            return null;
        }
        try {
            if ($formattedDate) {
                return Carbon::createFromFormat('d.m.Y H:i', "{$formattedDate} {$time}");
            }
            if ($date) {
                $year = date('Y');
                return Carbon::createFromFormat('M d Y H:i', "{$date} {$year} {$time}");
            }
            return Carbon::createFromFormat('H:i', $time);
        } catch (\Exception $e) {
            Log::warning('Failed to parse match time', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function extractEventsByType(array $events, string $type): array
    {
        $filteredEvents = [];
        
        foreach ($events as $event) {
            if (($event['type'] ?? null) === $type) {
                $filteredEvents[] = [
                    'minute' => $event['minute'] ?? null,
                    'team' => $event['team'] ?? null,
                    'player' => $event['player'] ?? null,
                    'result' => $event['result'] ?? null,
                    'player_id' => $event['playerId'] ?? null,
                    'assist' => $event['assist'] ?? null,
                    'assist_id' => $event['assistid'] ?? null,
                    'event_id' => $event['eventid'] ?? null,
                ];
            }
        }

        return $filteredEvents;
    }

    protected function sanitizeScore(?string $score): ?string
    {
        if (!$score) {
            return null;
        }
        $trimmed = trim($score);
        return in_array($trimmed, ['[-]', '[- ]', ' - ']) ? null : $trimmed;
    }

protected function normalizeStatus(string $status): string
{
    $status = strtolower(trim($status));
    Log::debug('Normalizing status', ['status' => $status]);

    // If it's a time format like "19:30" or "7:45"
    if (preg_match('/^\d{1,2}:\d{2}$/', $status)) {
        return 'scheduled';
    }

    // If it's numeric or like "45+", "90+3", etc.
    if (is_numeric($status) || preg_match('/^\d+\+?\d*$/', $status)) {
        return 'live';
    }

    return match ($status) {
        'notstarted', 'ns', 'scheduled' => 'scheduled',
        'live', 'inprogress', '1h', '2h', 'ht', 'et' => 'live',
        'ft', 'finished', 'end', 'postponed', 'canceled' => 'finished',
        default => 'unknown',
    };
}



protected function parseCricketScheduleMatches(string $xmlString): array
{
    $matches = [];

    $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) {
        Log::warning('Invalid XML received from Cricket API.');
        return $matches;
    }

    foreach ($xml->category as $category) {
        foreach ($category->match as $match) {
            $matches[] = [
                'category' => (string) $category['name'],
                'match_id' => (string) $match['id'],
                'date' => (string) $match['date'],
                'time' => (string) $match['time'],
                'status' => (string) $match['status'],
                'venue' => (string) $match['venue'],
                'home_team' => (string) $match->localteam['name'],
                'away_team' => (string) $match->visitorteam['name'],
            ];
        }
    }

    return $matches;
}


}




