<?php
namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MatchNormalizer
{
    public static function parseMatchStartTime(?string $time): ?Carbon
{
    if (!$time) return null;

    try {
        $year = now()->year;
        return Carbon::createFromFormat('M d, h:i A', $time)
            ->year($year)
            ->setTimezone('Asia/Kathmandu');
    } catch (\Exception $e) {
        Log::warning('Failed to parse match start time', ['input' => $time, 'error' => $e->getMessage()]);
        return null;
    }
}
    public static function extractExternalId(string $url): ?string
    {
        if (preg_match('/\/(\d+)\//', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
