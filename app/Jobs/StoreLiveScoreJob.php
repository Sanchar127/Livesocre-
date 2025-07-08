<?php

namespace App\Jobs;

use App\Models\Fixture;
use App\Models\Sport;
use App\Models\Matches;
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
    protected int $cacheMinutes = 1440; // 1 day cache
    protected int $maxRetries = 3;
    protected int $retryDelay = 60; // seconds
    protected ?int $sportId = null; // Store sport ID

    public function handle(): void
    {
        try {
            Log::info('Starting live scores processing job');
            
            // Temporary cache clearing for debugging
            Cache::forget('goalserve_league_mappings');
            
            // Fetch league mappings and sport
            $leagueData = $this->fetchLeagueMappings();
            $leagues = $leagueData['leagues'];
            $this->sportId = $leagueData['sport_id'];
            Log::info('Successfully fetched league mappings', ['count' => count($leagues)]);

            // Skip processing matches if no leagues are available
            if (empty($leagues)) {
                Log::warning('No leagues found, skipping match processing');
                return;
            }

            $matchesData = $this->fetchLiveScores();
            Log::info('Successfully fetched live scores', [
                'sport' => $matchesData['sport'],
                'match_count' => count($matchesData['matches'])
            ]);

            $processedCount = 0;
            foreach ($matchesData['matches'] as $matchData) {
                try {
                    $this->processMatch($matchData, $leagues);
                    $processedCount++;
                } catch (\Exception $e) {
                    Log::error('Failed to process match', [
                        'match_data' => $matchData,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            Log::info('Completed live scores processing', [
                'total_matches' => count($matchesData['matches']),
                'processed_matches' => $processedCount,
                'failed_matches' => count($matchesData['matches']) - $processedCount
            ]);

        } catch (\Throwable $e) {
            Log::error('Live scores job failed', [
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($this->attempts() < $this->maxRetries) {
                $this->release($this->retryDelay);
            } else {
                Log::critical('Max retries reached for live scores job');
            }
        }
    }

    protected function fetchLeagueMappings(): array
    {
        return Cache::remember('goalserve_league_mappings', $this->cacheMinutes, function () {
            $url = "https://www.goalserve.com/getfeed/0ddaa180b8bc4de749a508ddbacda0d5/soccerfixtures/data/mapping";
            
            Log::debug('Fetching league mappings', ['url' => $url]);
            
            $response = Http::timeout(30)->retry(3, 1000)->get($url);

            if ($response->failed()) {
                Log::error('Failed to fetch league mappings', ['status' => $response->status()]);
                throw new \Exception("Failed to fetch league mappings. Status: {$response->status()}");
            }

            // Log raw response for debugging
            $rawResponse = $response->body();
            Log::debug('Raw league mappings response', ['response' => substr($rawResponse, 0, 1000)]);

            $xml = $this->parseXml($rawResponse);
            if (!$xml) {
                Log::error('Invalid XML received for league mappings');
                throw new \Exception("Invalid XML received for league mappings");
            }

            // Extract and save sport
            $sport = isset($xml['sport']) ? (string)$xml['sport'] : 'soccer';
            $sportRecord = Sport::firstOrCreate(['name' => ucfirst(strtolower($sport))]);
            Log::debug('Saved sport from league mappings', ['sport' => $sport, 'sport_id' => $sportRecord->id]);

            $leagues = [];
            
            // Handle both direct mappings and fixtures->mappings structure
            if (isset($xml->mapping)) {
                foreach ($xml->mapping as $mapping) {
                    $this->processMappingNode($mapping, $leagues, $sportRecord->id);
                }
            } elseif (isset($xml->fixtures) && isset($xml->fixtures->mapping)) {
                foreach ($xml->fixtures->mapping as $mapping) {
                    $this->processMappingNode($mapping, $leagues, $sportRecord->id);
                }
            } else {
                Log::warning('No league mappings found in XML', [
                    'root_node' => $xml->getName(),
                    'children' => array_map(fn($node) => $node->getName(), $xml->children())
                ]);
                // Return empty leagues instead of throwing an exception
                return [
                    'leagues' => [],
                    'sport_id' => $sportRecord->id
                ];
            }

            Log::debug('Processed league mappings', ['leagues' => $leagues]);
            return [
                'leagues' => $leagues,
                'sport_id' => $sportRecord->id
            ];
        });
    }

    protected function fetchLiveScores(): array
    {
        $url = "https://www.goalserve.com/getfeed/0ddaa180b8bc4de749a508ddbacda0d5/soccernew/live";
        
        Log::debug('Fetching live scores', ['url' => $url]);
        $response = Http::timeout(30)->retry(3, 1000)->get($url);

        if ($response->failed()) {
            Log::error('Failed to fetch live scores', ['status' => $response->status()]);
            throw new \Exception("Failed to fetch live scores. Status: {$response->status()}");
        }

        $xml = $this->parseXml($response->body());
        if (!$xml) {
            Log::error('Invalid XML received for live scores');
            throw new \Exception("Invalid XML received for live scores");
        }

        $sport = isset($xml['sport']) ? (string)$xml['sport'] : 'soccer';
        $matches = [];

        if (isset($xml->category)) {
            foreach ($xml->category as $category) {
                $categoryAttrs = $category->attributes();
                $categoryName = (string)$categoryAttrs->name;
                $categoryId = (string)$categoryAttrs->id;

                if (isset($category->matches->match)) {
                    foreach ($category->matches->match as $match) {
                        $matchData = $this->parseMatchElement($match);
                        $matchData['category'] = $categoryName;
                        $matchData['league_id'] = $categoryId;
                        $matches[] = $matchData;
                    }
                }
            }
        } else {
            Log::warning('No matches found in live scores XML', [
                'root_node' => $xml->getName(),
                'children' => array_map(fn($node) => $node->getName(), $xml->children())
            ]);
        }

        return [
            'sport' => ucfirst(strtolower($sport)),
            'matches' => $matches
        ];
    }

    protected function processMappingNode(SimpleXMLElement $mapping, array &$leagues, int $sportId): void
    {
        $attrs = $mapping->attributes();
        if ($attrs) {
            $id = (string)$attrs->id;
            $name = (string)$attrs->name;
            $country = (string)$attrs->country;
            $season = (string)$attrs->season;

            if ($id && $name) {
                $leagues[$id] = $name;

                // Save to fixtures table
                Fixture::updateOrCreate(
                    ['external_id' => $id],
                    [
                        'name' => $name,
                        'country' => $country,
                        'season' => $season,
                        'sport_id' => $sportId // Use passed sport_id
                    ]
                );

                Log::debug('Saved fixture mapping', ['id' => $id, 'name' => $name, 'sport_id' => $sportId]);
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

        // Parse match attributes
        foreach ($match->attributes() as $key => $value) {
            $data['@attributes'][$key] = (string)$value;
        }

        // Parse local team
        if (isset($match->localteam)) {
            foreach ($match->localteam->attributes() as $key => $value) {
                $data['localteam']['@attributes'][$key] = (string)$value;
            }
        }

        // Parse visitor team
        if (isset($match->visitorteam)) {
            foreach ($match->visitorteam->attributes() as $key => $value) {
                $data['visitorteam']['@attributes'][$key] = (string)$value;
            }
        }

        // Parse events
        if (isset($match->events->event)) {
            foreach ($match->events->event as $event) {
                $eventData = [];
                foreach ($event->attributes() as $key => $value) {
                    $eventData[$key] = (string)$value;
                }
                $data['events'][] = $eventData;
            }
        }

        // Parse score elements
        foreach (['ht', 'ft', 'et'] as $period) {
            if (isset($match->$period) && isset($match->$period['score'])) {
                $data[$period]['score'] = (string)$match->$period['score'];
            }
        }

        return $data;
    }

    protected function processMatch(array $matchData, array $leagues): void
    {
        $matchId = $matchData['@attributes']['id'] ?? null;
        if (!$matchId) {
            throw new \Exception("Match ID is missing");
        }

        $attributes = $matchData['@attributes'];
        $homeTeam = $matchData['localteam']['@attributes'];
        $awayTeam = $matchData['visitorteam']['@attributes'];
        $leagueId = $matchData['league_id'] ?? null;
        $fixId = $attributes['fix_id'] ?? null;

        if (!$fixId) {
            Log::warning('Skipping match with missing fix_id', ['match_id' => $matchId]);
            return;
        }

        if (!$leagueId) {
            Log::warning('Skipping match with missing league_id', ['match_id' => $matchId]);
            return;
        }

        if (!$this->sportId) {
            Log::error('Sport ID not set for match processing', ['match_id' => $matchId]);
            throw new \Exception("Sport ID is missing");
        }

        // Find or create the corresponding fixture using fix_id
        $fixture = Fixture::firstOrCreate(
            ['external_id' => $fixId],
            [   
                'sport_id' => $this->sportId, // Add sport_id to fixture
                'league_external_id' => $leagueId,
                'name' => $leagues[$leagueId] ?? $matchData['category'] ?? 'Unknown League',
                'country' => $this->extractCountryFromCategory($matchData['category'] ?? ''),
                'season' => $this->extractSeasonFromDate($attributes['date'] ?? null),
            ]
        );

        Log::debug('Assigned fixture to match', [
            'match_id' => $matchId,
            'fixture_id' => $fixture->id,
            'external_id' => $fixId,
            'league_external_id' => $leagueId,
            'fixture_name' => $fixture->name,
            'is_new' => $fixture->wasRecentlyCreated
        ]);

        // Prepare match data
        $matchRecord = [
            'sport_id' => $this->sportId, // Use pre-saved sport ID
            'fixture_id' => $fixture->id,
            'external_match_id' => $matchId,
            'home_team' => $homeTeam['name'] ?? 'Unknown',
            'away_team' => $awayTeam['name'] ?? 'Unknown',
            'status' => $this->normalizeStatus($attributes['status'] ?? 'unknown'),
            'start_time' => $this->parseMatchTime(
                $attributes['date'] ?? null,
                $attributes['formatted_date'] ?? null,
                $attributes['time'] ?? null
            ),
            'league' => $leagues[$leagueId] ?? $matchData['category'] ?? 'Unknown League',
        ];

        // Update or create match record
        $match = Matches::updateOrCreate(
            ['external_match_id' => $matchId],
            $matchRecord
        );

        // Prepare score data
        $scoreData = [
            'home_score' => (int)($homeTeam['goals'] ?? 0),
            'away_score' => (int)($awayTeam['goals'] ?? 0),
            'half_time_score' => $this->sanitizeScore($matchData['ht']['score'] ?? null),
            'full_time_score' => $this->sanitizeScore($matchData['ft']['score'] ?? null),
            'extra_time_score' => $this->sanitizeScore($matchData['et']['score'] ?? null),
            'match_status' => $matchRecord['status'],
            'goal_events' => $this->extractEventsByType($matchData['events'], 'goal'),
            'red_card_events' => $this->extractEventsByType($matchData['events'], 'redcard'),
            'yellow_card_events' => $this->extractEventsByType($matchData['events'], 'yellowcard'),
            'substitution_events' => $this->extractEventsByType($matchData['events'], 'subst'),
        ];

        // Update or create score record
        Score::updateOrCreate(
            ['match_id' => $match->id],
            ['score_data' => $scoreData]
        );

        Log::debug('Processed match successfully', [
            'match_id' => $matchId,
            'home_team' => $matchRecord['home_team'],
            'away_team' => $matchRecord['away_team'],
            'league' => $matchRecord['league'],
            'fixture_id' => $fixture->id
        ]);
    }

    // Helper method to extract country from category
    protected function extractCountryFromCategory(string $category): string
    {
        // Example: "England: Premier League" => "England"
        $parts = explode(':', $category);
        return trim($parts[0] ?? 'Unknown');
    }

    // Helper method to extract season from date
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
        // Enable user error handling
        libxml_use_internal_errors(true);
        
        // Try to parse with simplexml
        $xml = simplexml_load_string($xmlString);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                Log::error('XML parse error', [
                    'level' => $error->level,
                    'code' => $error->code,
                    'message' => trim($error->message),
                    'line' => $error->line,
                    'column' => $error->column
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
            // Try with formatted date first (e.g., "07.07.2025")
            if ($formattedDate) {
                return Carbon::createFromFormat('d.m.Y H:i', "{$formattedDate} {$time}");
            }
            
            // Fallback to regular date (e.g., "Jul 07")
            if ($date) {
                $year = date('Y');
                return Carbon::createFromFormat('M d Y H:i', "{$date} {$year} {$time}");
            }
            
            // Just time if no date available
            return Carbon::createFromFormat('H:i', $time);
            
        } catch (\Exception $e) {
            Log::warning('Failed to parse match time', [
                'date' => $date,
                'formatted_date' => $formattedDate,
                'time' => $time,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    protected function extractEventsByType(array $events, string $type): array
    {
        return array_values(array_filter(array_map(function ($event) use ($type) {
            if (($event['type'] ?? null) === $type) {
                return [
                    'minute' => $event['minute'] ?? null,
                    'team' => $event['team'] ?? null,
                    'player' => $event['player'] ?? null,
                    'result' => $event['result'] ?? null,
                ];
            }
            return null;
        }, $events)));
    }

    protected function sanitizeScore(?string $score): ?string
    {
        if (!$score) {
            return null;
        }
        
        $trimmed = trim($score);
        return in_array($trimmed, ['[-]', '[ - ]', ' - ']) ? null : $trimmed;
    }

    protected function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        
        $statusMap = [
            'ht' => 'halftime',
            'ft' => 'finished',
            'aet' => 'after_extra_time',
            'pen' => 'penalties',
            'canceled' => 'cancelled',
            'postp' => 'postponed',
        ];
        
        return $statusMap[$status] ?? $status;
    }
}