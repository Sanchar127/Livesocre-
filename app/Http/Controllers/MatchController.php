<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Matches;
use App\Models\Sport;

class MatchController extends Controller
{
    
    public function index(Request $request)
{
    $slug = $request->query('sport', 'cricket'); // Default to cricket

    // Fetch sport ID from slug
    $sport = Sport::where('slug', $slug)->firstOrFail();

    // Get matches filtered by sport
    $matches = Matches::where('sport_id', $sport->id)
                    ->orderBy('start_time', 'desc')
                    ->paginate(10);

    return view('matches.index', compact('matches', 'slug'));
}

public function live(Request $request, $sport)
{
    // Look up the sport by slug
    $sportModel = Sport::where('slug', $sport)->firstOrFail();

    // Fetch live matches for that sport
    $matches = Matches::with(['fixture', 'score'])
        ->where('sport_id', $sportModel->id)
        ->where('status', 'live')
        ->orderBy('start_time', 'desc')
        ->paginate(10);

    return view('matches.live', [
        'matches' => $matches,
        'slug' => $sport
    ]);
}

public function getMatchesByCountry(Request $request, $country)
{
    $sportId = $request->get('sport_id'); // Optional filter via query param

    $matches = Matches::when($sportId, fn ($q) => $q->where('sport_id', $sportId))
        ->whereHas('fixture', function ($query) use ($country) {
            $query->where('country', $country);
        })
        ->with(['fixture', 'sport'])
        ->get();

    return view('matches.by_country.index', compact('matches', 'country', 'sportId'));
}




}




