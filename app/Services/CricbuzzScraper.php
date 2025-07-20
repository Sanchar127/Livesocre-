<?php

namespace App\Services;

use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;
use DOMDocument;
use DOMXPath;
use Exception;

class CricbuzzScraper
{
    public function fetchSeriresOrTournament(): array
    {
        $url = 'https://www.cricbuzz.com/cricket-schedule/series/all';
        $series = [];

        try {
            $html = Browsershot::url($url)
                ->setOption('waitUntil', 'load')
                ->timeout(120)
                ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36')
                ->bodyHtml();

            // Log::debug('Rendered HTML body: ' . substr($html, 0, 5000));

            $crawler = new Crawler($html);

            $crawler->filter('.cb-col-100.cb-col')->each(function (Crawler $monthSection) use (&$series) {
                try {
                    $month = $monthSection->filter('.cb-col-16.cb-col.text-bold.cb-mnth')->text();

                    $monthSection->filter('.cb-sch-lst-itm')->each(function (Crawler $seriesNode) use (&$series, $month) {
                        try {
                            $linkNode = $seriesNode->filter('a.text-black.text-hvr-underline')->first();
                            if (!$linkNode) return;

                            $seriesName = trim($linkNode->filter('span.text-black')->text());
                            $relativeUrl = $linkNode->attr('href');
                            $fullUrl = $relativeUrl ? 'https://www.cricbuzz.com' . $relativeUrl : null;

                            $seriesId = null;
                            $seriesSlug = null;
                            if ($relativeUrl && preg_match('#/cricket-series/(\d+)/([\w-]+)#', $relativeUrl, $matches)) {
                                $seriesId = $matches[1];
                                $seriesSlug = $matches[2];
                            }

                            $dateRange = $seriesNode->filter('.text-gray.cb-font-12')->text();

                            if ($month && $seriesName && $fullUrl) {
                                $series[] = [
                                    'month' => $month,
                                    'Externalseries_id' => $seriesId,
                                    'series_slug' => $seriesSlug,
                                    'series_name' => $seriesName,
                                    'url' => $fullUrl,
                                    'date_range' => $dateRange ?: null,
                                ];
                            }
                        } catch (Exception $e) {
                            Log::warning('Error parsing series node: ' . $e->getMessage());
                        }
                    });
                } catch (Exception $e) {
                    Log::warning('Error parsing month section: ' . $e->getMessage());
                }
            });

            return $series;
        } catch (Exception $e) {
            Log::error('Exception while scraping Cricbuzz using Browsershot: ' . $e->getMessage());
            return [];
        }
    }

  public function fetchMatches(int $seriesId, string $seriesSlug): array
{
    $matches = [];
    $url = "https://www.cricbuzz.com/cricket-series/{$seriesId}/{$seriesSlug}/matches";

    Log::info("Starting to fetch matches from URL: {$url}");

    try {
        $html = Browsershot::url($url)
            ->setOption('waitUntil', 'networkidle0')
            ->waitUntilNetworkIdle()
            ->timeout(120)
            ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36')
            ->bodyHtml();

        $crawler = new Crawler($html);
        $matchNodes = $crawler->filter('.cb-series-matches');

        Log::info("Found " . $matchNodes->count() . " match blocks to process for series ID: {$seriesId}");

        $matchNodes->each(function (Crawler $matchNode, $index) use (&$matches) {
            try {
                $title = $matchNode->filter('.cb-srs-mtchs-tm a span')->first()->text();
                $matchUrl = 'https://www.cricbuzz.com' . $matchNode->filter('.cb-srs-mtchs-tm a')->first()->attr('href');
                $venue = $matchNode->filter('.cb-srs-mtchs-tm .text-gray')->first()->text();

                // Extract date information
                $dateText = 'N/A';
                if ($matchNode->filter('.schedule-date span')->count() > 0) {
                    $dateText = $matchNode->filter('.schedule-date span')->first()->text();
                } elseif ($matchNode->filter('.cb-col-25.cb-col.pad10 span')->count() > 0) {
                    $dateText = $matchNode->filter('.cb-col-25.cb-col.pad10 span')->first()->text();
                }

                // Extract time information
                $timeInfo = $matchNode->filter('.cb-font-12.text-gray')->count() > 0
                    ? trim($matchNode->filter('.cb-font-12.text-gray')->first()->text())
                    : 'N/A';

                // Combine date and time
                $datetimeInfo = trim("$dateText, $timeInfo");

                // Attempt to extract status/result text
                $statusText = $matchNode->filter('.cb-text-complete, .cb-text-live, .cb-text-upcoming,cb-text-inningsbreak,.cb-text-inprogress,cb-text-preview,cb-text-toss')->count() > 0
                    ? $matchNode->filter('.cb-text-complete, .cb-text-live, .cb-text-upcoming,.cb-text-inningsbreak,.cb-text-inprogress,cb-text-preview,cb-text-toss')->first()->text()
                    : '';

                // Rest of your existing code for match status, teams, etc...
                $matchStatus = 'unknown';
                $statusLower = strtolower($statusText);

                if (
                    str_contains($statusLower, 'won') ||
                    str_contains($statusLower, 'draw') ||
                    str_contains($statusLower, 'no result') ||
                    str_contains($statusLower, 'tie')
                ) {
                    $matchStatus = 'completed';
                } elseif (
                    str_contains($statusLower, 'need') ||
                    str_contains($statusLower, 'delay') ||
                    str_contains($statusLower, 'day') ||
                    str_contains($statusLower, 'stumb') ||
                    str_contains($statusLower, 'live')||
                    str_contains($statusLower, 'inprogress')||
                    str_contains($statusLower, 'toss')||
                    str_contains($statusLower, 'inningsbreak')
                ) {
                    $matchStatus = 'live';
                } elseif (
                    str_contains($statusLower, 'starts') ||
                    preg_match('/starts at/i', $statusLower)
                ) {
                    $matchStatus = 'upcoming';
                }

                // Extract team1 and team2 from title
                $team1 = $team2 = 'N/A';
                if (preg_match('/^(.*?)\s+vs\s+(.*?),/', $title, $teamMatches)) {
                    $team1 = trim($teamMatches[1]);
                    $team2 = trim($teamMatches[2]);
                }

                // Extract match ID and slug
                $matchId = $this->extractMatchId($matchUrl);
                $matchSlug = $this->extractMatchSlug($matchUrl);

                // Build match data
                $matches[] = [
                    'title' => trim($title),
                    'venue' => trim($venue),
                    'result' => trim($statusText),
                    'match_status' => $matchStatus,
                    'datetime_info' => $datetimeInfo, // Updated to include combined date and time
                    'date' => $dateText, // Separate date field if needed
                    'time' => $timeInfo, // Separate time field if needed
                    'url' => $matchUrl,
                    'match_id' => $matchId,
                    'match_slug' => $matchSlug,
                    'squad_url' => "https://www.cricbuzz.com/cricket-match-squads/{$matchId}/{$matchSlug}",
                    'scorecard_url' => "https://www.cricbuzz.com/live-cricket-scorecard/{$matchId}/{$matchSlug}",
                    'team1' => $team1,
                    'team2' => $team2,
                ];

                Log::info("Parsed match: {$team1} vs {$team2} | Date: {$dateText} | Time: {$timeInfo} | Status: {$matchStatus}");
            } catch (\Exception $e) {
                Log::warning("Error processing match block at index {$index}: " . $e->getMessage());
            }
        });

        Log::info("Completed parsing matches. Total matches found: " . count($matches));
        return $matches;
    } catch (\Exception $e) {
        Log::error("Exception while scraping matches for series ID {$seriesId}: " . $e->getMessage());
        return [];
    }
}

  public function fetchSquad(string $matchId, string $matchSlug): array
{
    $url = "https://www.cricbuzz.com/cricket-match-squads/{$matchId}/{$matchSlug}";
    Log::info("Fetching squad from URL: {$url}");
    $squads = [];

    try {
        $html = Browsershot::url($url)
            ->setOption('waitUntil', 'networkidle0')
            ->waitUntilNetworkIdle() // Add additional wait
            ->timeout(90) // Increase timeout
            ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36')
            ->bodyHtml();

        $crawler = new Crawler($html);

        // First check if we have the squad container
        if ($crawler->filter('.cb-col.cb-col-100.cb-teams-hdr')->count() === 0) {
            Log::warning("No squad header found in HTML");
            return [];
        }

        // Get team names with more flexible selector
        $teamNames = $crawler->filter('.cb-teams-hdr a[class^="cb-team"]')
            ->each(function (Crawler $node) {
                return trim($node->filter('div.pad5:last-child')->text());
            });

        if (count($teamNames) < 2) {
            Log::warning("Couldn't find both team names");
            return [];
        }

        // Process players with more defensive checks
        $processPlayers = function (Crawler $playerNode, string $side) {
            try {
                $nameNode = $playerNode->filter(".cb-player-name-{$side} div")->first();
                $roleNode = $playerNode->filter('.cb-font-12.text-gray');

                return [
                    'name' => $nameNode->count() ? trim($nameNode->text()) : 'Unknown',
                    'role' => $roleNode->count() ? trim($roleNode->text()) : 'Unknown'
                ];
            } catch (Exception $e) {
                Log::warning("Error processing player node: " . $e->getMessage());
                return null;
            }
        };

        // Get left team players
        $leftTeamPlayers = $crawler->filter('.cb-play11-lft-col .cb-player-card-left')
            ->each(function (Crawler $playerNode) use ($processPlayers) {
                return $processPlayers($playerNode, 'left');
            });

        // Get right team players
        $rightTeamPlayers = $crawler->filter('.cb-play11-rt-col .cb-player-card-right')
            ->each(function (Crawler $playerNode) use ($processPlayers) {
                return $processPlayers($playerNode, 'right');
            });

        // Filter out null entries
        $leftTeamPlayers = array_filter($leftTeamPlayers);
        $rightTeamPlayers = array_filter($rightTeamPlayers);

        $squads = [
            [
                'team' => $teamNames[0],
                'players' => array_values($leftTeamPlayers)
            ],
            [
                'team' => $teamNames[1],
                'players' => array_values($rightTeamPlayers)
            ]
        ];

        return $squads;
    } catch (Exception $e) {
        Log::error("Failed to fetch squads for match {$matchId}: " . $e->getMessage());
        return [];
    }
}
 public function fetchScorecard(string $matchId, string $matchSlug): array
    {
        $url = "https://www.cricbuzz.com/live-cricket-scorecard/{$matchId}/{$matchSlug}";
        Log::info("Fetching scorecard from URL: {$url}");

        $scorecard = [
            'result' => '',
            'innings' => []
        ];

        $html = $this->getHtmlContent($url);
        if (empty($html)) {
            Log::error("Empty HTML returned from getHtmlContent for URL: {$url}");
            return $scorecard;
        }

        // Create DOMDocument and load HTML
        $dom = new DOMDocument();
        @$dom->loadHTML($html); // Suppress warnings for malformed HTML
        $xpath = new DOMXPath($dom);

        // Get match result
        $resultNode = $xpath->query("//div[contains(@class, 'cb-scrcrd-status')]");
        if ($resultNode->length > 0) {
            $scorecard['result'] = trim($resultNode->item(0)->nodeValue);
        }

        // Process each innings
        $inningsNodes = $xpath->query("//div[starts-with(@id, 'innings_')]");
        foreach ($inningsNodes as $inningsNode) {
            $inningsData = [
                'team' => '',
                'score' => '',
                'batters' => [],
                'bowlers' => [],
                'fall_of_wickets' => [],
                'extras' => '',
                'total' => ''
            ];

            // Get innings header (team name and score)
            $headerNode = $xpath->query(".//div[contains(@class, 'cb-scrd-hdr-rw')]", $inningsNode);
            if ($headerNode->length > 0) {
                $spans = $xpath->query(".//span", $headerNode->item(0));
                if ($spans->length >= 2) {
                    $inningsData['team'] = trim($spans->item(0)->nodeValue);
                    $inningsData['score'] = trim($spans->item(1)->nodeValue);
                }
            }

            // Process batters
            $batterRows = $xpath->query(".//div[contains(@class, 'cb-scrd-itms') and .//div[contains(@class, 'cb-col-25')]]", $inningsNode);
            foreach ($batterRows as $row) {
                $label = trim($xpath->query(".//div[contains(@class, 'cb-col-60') or contains(@class, 'cb-col-25')]", $row)->item(0)->nodeValue ?? '');

                if (strpos($label, 'Extras') !== false || strpos($label, 'Total') !== false) {
                    continue;
                }

                $batter = [
                    'name' => trim($xpath->query(".//a[contains(@class, 'cb-text-link')]", $row)->item(0)->nodeValue ?? ''),
                    'dismissal' => trim($xpath->query(".//span[contains(@class, 'text-gray')]", $row)->item(0)->nodeValue ?? 'not out'),
                    'runs' => trim($xpath->query(".//div[contains(@class, 'cb-col-8')][1]", $row)->item(0)->nodeValue ?? '0'),
                    'balls' => trim($xpath->query(".//div[contains(@class, 'cb-col-8')][2]", $row)->item(0)->nodeValue ?? '0'),
                    'fours' => trim($xpath->query(".//div[contains(@class, 'cb-col-8')][3]", $row)->item(0)->nodeValue ?? '0'),
                    'sixes' => trim($xpath->query(".//div[contains(@class, 'cb-col-8')][4]", $row)->item(0)->nodeValue ?? '0'),
                    'strike_rate' => trim($xpath->query(".//div[contains(@class, 'cb-col-8')][5]", $row)->item(0)->nodeValue ?? '0.00')
                ];

                $inningsData['batters'][] = $batter;
            }

            // Process extras
            $extrasNode = $xpath->query(".//div[contains(@class, 'cb-scrd-itms') and contains(.//div, 'Extras')]", $inningsNode);
            if ($extrasNode->length > 0) {
                $extrasText = trim($xpath->query(".//div[contains(@class, 'cb-col-8')]", $extrasNode->item(0))->item(0)->nodeValue ?? '');
                $extrasDetails = trim($xpath->query(".//div[contains(@class, 'cb-col-32')]", $extrasNode->item(0))->item(0)->nodeValue ?? '');
                $inningsData['extras'] = $extrasText . $extrasDetails;
            }

            // Process total
            $totalNode = $xpath->query(".//div[contains(@class, 'cb-scrd-itms') and contains(.//div, 'Total')]", $inningsNode);
            if ($totalNode->length > 0) {
                $totalText = trim($xpath->query(".//div[contains(@class, 'cb-col-8')]", $totalNode->item(0))->item(0)->nodeValue ?? '');
                $totalDetails = trim($xpath->query(".//div[contains(@class, 'cb-col-32')]", $totalNode->item(0))->item(0)->nodeValue ?? '');
                $inningsData['total'] = $totalText . $totalDetails;
            }

            // Process bowlers
            $bowlerRows = $xpath->query(".//div[contains(@class, 'cb-scrd-itms') and .//div[contains(@class, 'cb-col-38')]]", $inningsNode);
            foreach ($bowlerRows as $row) {
                $bowler = [
                    'name' => trim($xpath->query(".//a[contains(@class, 'cb-text-link')]", $row)->item(0)->nodeValue ?? ''),
                    'overs' => trim($xpath->query(".//div[contains(@class, 'cb-col-8')][1]", $row)->item(0)->nodeValue ?? ''),
                    'maidens' => trim($xpath->query(".//div[contains(@class, 'cb-col-8')][2]", $row)->item(0)->nodeValue ?? ''),
                    'runs' => trim($xpath->query(".//div[contains(@class, 'cb-col-10')][1]", $row)->item(0)->nodeValue ?? ''),
                    'wickets' => trim($xpath->query(".//div[contains(@class, 'cb-col-8')][4]", $row)->item(0)->nodeValue ?? ''),
                    'no_balls' => trim($xpath->query(".//div[contains(@class, 'cb-col-8')][5]", $row)->item(0)->nodeValue ?? ''),
                    'wides' => trim($xpath->query(".//div[contains(@class, 'cb-col-8')][6]", $row)->item(0)->nodeValue ?? ''),
                    'economy' => trim($xpath->query(".//div[contains(@class, 'cb-col-10')][2]", $row)->item(0)->nodeValue ?? '')
                ];

                $inningsData['bowlers'][] = $bowler;
            }

            // Process fall of wickets
            $fowNode = $xpath->query(".//div[contains(@class, 'cb-scrd-sub-hdr') and contains(., 'Fall of Wickets')]/following-sibling::div[1]", $inningsNode);
            if ($fowNode->length > 0) {
                $fowText = trim($fowNode->item(0)->nodeValue);
                $wickets = explode(',', $fowText);
                foreach ($wickets as $wicket) {
                    $wicket = trim(preg_replace('/\s*\([^)]+\)/', '', $wicket)); // Remove player name for cleaner output
                    if (!empty($wicket)) {
                        $inningsData['fall_of_wickets'][] = $wicket;
                    }
                }
            }

            $scorecard['innings'][] = $inningsData;
        }

        return $scorecard;
    }

   public function fetchLeagueTable(int $seriesId, string $seriesSlug): array
{
    $url = "https://www.cricbuzz.com/cricket-series/{$seriesId}/{$seriesSlug}/points-table";
    $tableData = [];

    Log::info("Fetching league table from URL: {$url}");

    try {
        $html = Browsershot::url($url)
            ->setOption('waitUntil', 'networkidle0')
            ->waitUntilNetworkIdle(true)
            ->timeout(180)
            ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36')
            ->setDelay(5000)  // Increased delay to ensure full page load
            ->bodyHtml();

        // Save HTML for debugging
 

        $crawler = new Crawler($html);

        // 1. First try exact match from screenshot
        $table = $crawler->filter('h3.cb-mat-mnu-wnp:contains("Points Table") + table.cb-srs-prts');
        
        // 2. Fallback to any points table structure
        if ($table->count() === 0) {
            $table = $crawler->filter('table.cb-srs-pnts-tbl, table.cb-srs-prts, table[class*="points"]');
        }

        // 3. Final fallback to any table under points table heading
        if ($table->count() === 0) {
            $table = $crawler->filter('h3:contains("Points Table") + table');
        }

        if ($table->count() === 0) {
            Log::warning("No points table found after multiple attempts");
            return [];
        }

        // Get headers - more resilient approach
        $headers = [];
        $headerRow = $table->filter('thead tr')->first();
        if ($headerRow->count() === 0) {
            $headerRow = $table->filter('tr')->first();
        }

        $headerRow->filter('th, td')->each(function (Crawler $node) use (&$headers) {
            $headerText = trim($node->text());
            // Use title attribute if available
            $headerText = $node->attr('title') ?: $headerText;
            $headers[] = $headerText;
        });

        if (count($headers) < 5) {
            Log::warning("Insufficient headers found", ['headers' => $headers]);
            return [];
        }

        // Process rows
        $table->filter('tbody tr, tr:not(:first-child)')->each(function (Crawler $row) use (&$tableData, $headers) {
            if ($row->filter('th')->count() > 0) {
                return; // Skip header rows
            }

            $rowData = [];
            
            // Team name - multiple fallbacks
            $teamCell = $row->filter('td.cb-srs-prts-name, td.cb-srs-pnts-name, td:first-child')->first();
            if ($teamCell->count() === 0) {
                return;
            }
            
            $rowData['team'] = trim($teamCell->text());
            
            // Other columns
            $columns = $row->filter('td')->slice(1);
            $columnIndex = 0;
            
            foreach ($headers as $index => $header) {
                if ($index === 0) continue; // Skip team column
                
                $column = $columns->eq($columnIndex);
                if ($column->count() === 0) break;
                
                $value = trim($column->text());
                $cleanHeader = strtolower(preg_replace('/[^a-z]/i', '', $header));
                
                switch ($cleanHeader) {
                    case 'mat': $rowData['matches_played'] = $value; break;
                    case 'won': case 'mon': $rowData['matches_won'] = $value; break;
                    case 'lost': $rowData['matches_lost'] = $value; break;
                    case 'tied': $rowData['matches_tied'] = $value; break;
                    case 'nr': $rowData['no_result'] = $value; break;
                    case 'pts': $rowData['points'] = $value; break;
                    case 'nrr': $rowData['net_run_rate'] = $value; break;
                    default: $rowData[strtolower(str_replace(' ', '_', $header))] = $value;
                }
                
                $columnIndex++;
            }
            
            if (count($rowData) > 1) { // Must have at least team + one other field
                $tableData[] = $rowData;
            }
        });

        if (empty($tableData)) {
            Log::warning("No valid team data found in table");
            return [];
        }

        Log::info("Successfully parsed league table", [
            'team_count' => count($tableData),
            'first_team' => $tableData[0]['team'] ?? 'none'
        ]);
        
        return $tableData;
        
    } catch (Exception $e) {
        Log::error("Failed to fetch league table: " . $e->getMessage());
        return [];
    }
}
    private function getHtmlContent(string $url): string
    {
        try {
            return Browsershot::url($url)
                ->setOption('waitUntil', 'networkidle0')
                ->timeout(120)
                ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36')
                ->bodyHtml();
        } catch (\Exception $e) {
            Log::error("Failed to fetch HTML for URL {$url}: " . $e->getMessage());
            return '';
        }
    }



    private static function extractMatchId(string $url): ?string
    {
        if (preg_match('#/(\d+)/#', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private static function extractMatchSlug(string $url): ?string
    {
        if (preg_match('#/\d+/([^/]+)$#', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

  
}
