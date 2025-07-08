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

            $leagueData = $this->fetchLeagueMappings();
            $leagues = $leagueData['leagues'];

            Log::info('League mappings fetched', [
                'count' => count($leagues),
                'sport_id' => $this->sport->id,
            ]);

            $matchesData = $this->fetchLiveScores();
            Log::info('Live scores fetched', [
                'match_count' => count($matchesData['matches']),
                'sport' => $this->sport->name,
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
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            Log::info('Live scores processing completed', [
                'total_matches' => count($matchesData['matches']),
                'processed_matches' => $processedCount,
                'sport' => $this->sport->name,
            ]);

        } catch (\Throwable $e) {
            Log::error('Job processing failed', [
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'sport' => $this->sport->name,
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->attempts() < $this->maxRetries) {
                $this->release($this->retryDelay);
            } else {
                Log::critical('Max retries reached for job', ['sport' => $this->sport->name]);
            }
        }
    }

    protected function fetchLeagueMappings(): array
    {
        try {
            $cacheKey = "goalserve_league_mappings_{$this->sport->slug}";
            return Cache::remember($cacheKey, $this->cacheMinutes, function () {
                $url = "{$this->baseUrl}/{$this->apiKey}/{$this->sport->slug}fixtures/data/mapping";
                Log::debug('Fetching league mappings', ['url' => $url]);

                $response = Http::timeout(30)->retry(3, 1000)->get($url);

                if ($response->failed()) {
                    Log::warning('League mappings API failed', [
                        'status' => $response->status(),
                    ]);
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

                return [
                    'leagues' => $leagues,
                    'sport_id' => $this->sport->id,
                ];
            });
        } catch (\Exception $e) {
            Log::error('League mappings fetch exception', [
                'error' => $e->getMessage(),
            ]);
            return ['leagues' => [], 'sport_id' => $this->sport->id];
        }
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

    protected function processMatch(array $matchData, array $leagues): void
    {
        try {
            $matchId = (string)($matchData['@attributes']['id'] ?? null);
            if (!$matchId) {
                throw new \Exception("Match ID is missing");
            }

            $attributes = $matchData['@attributes'];
            $homeTeam = $matchData['localteam']['@attributes'] ?? [];
            $awayTeam = $matchData['visitorteam']['@attributes'] ?? [];
            $leagueId = $matchData['league_id'] ?? null;
            $fixId = $attributes['fix_id'] ?? null;

            if (!$fixId || !$leagueId) {
                Log::warning('Skipping match with missing fix_id or league_id', [
                    'match_id' => $matchId,
                ]);
                return;
            }

            // Create or update fixture
            $fixture = Fixture::firstOrCreate(
                ['external_id' => $fixId],
                [
                    'sport_id' => $this->sport->id,
                    'league_external_id' => $leagueId,
                    'name' => $leagues[$leagueId] ?? $matchData['category'] ?? 'Unknown League',
                    'country' => $this->extractCountryFromCategory($matchData['category'] ?? ''),
                    'season' => $this->extractSeasonFromDate($attributes['date'] ?? null),
                ]
            );

            // Prepare match data
            $matchRecord = [
                'sport_id' => $this->sport->id,
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
                'metadata' => [
                    'venue' => $attributes['venue'] ?? null,
                    'live_stats' => $this->parseLiveStats($attributes['live_stats']['value'] ?? null),
                ],
            ];

            // Create or update match
            $match = MatchModel::updateOrCreate(
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
                'match_status' => $this->normalizeStatus($attributes['status'] ?? 'unknown'),
                'goal_events' => $this->extractEventsByType($matchData['events'] ?? [], 'goal'),
                'red_card_events' => $this->extractEventsByType($matchData['events'] ?? [], 'redcard'),
                'yellow_card_events' => $this->extractEventsByType($matchData['events'] ?? [], 'yellowcard'),
                'substitution_events' => $this->extractEventsByType($matchData['events'] ?? [], 'substitution'),
            ];

            // Debug score data
            Log::debug('Score data prepared', ['match_id' => $matchId, 'score_data' => $scoreData]);

            // Create or update score
            Score::updateOrCreate(
                ['match_id' => $match->id],
                ['score_data' => $scoreData]
            );

            Log::info('Match processed successfully', [
                'match_id' => $matchId,
                'home_team' => $homeTeam['name'] ?? null,
                'away_team' => $awayTeam['name'] ?? null,
                'score' => ($homeTeam['goals'] ?? 0) . '-' . ($awayTeam['goals'] ?? 0),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process match', [
                'match_data' => $matchData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
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
        
        return match ($status) {
            'ht' => 'halftime',
            'ft' => 'finished',
            'aet' => 'after_extra_time',
            'pen' => 'penalties',
            'canceled' => 'cancelled',
            'postp' => 'postponed',
            default => $status,
        };
    }
}