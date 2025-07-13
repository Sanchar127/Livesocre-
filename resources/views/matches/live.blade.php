<h2>Live {{ ucfirst($slug) }} Matches</h2>
<a href="{{ route('matches.live', ['sport' => 'cricket']) }}">Live Cricket</a>
<a href="{{ route('matches.live', ['sport' => 'soccer']) }}">Live Football</a>

@forelse($matches as $match)
    <div style="border: 1px solid #ccc; padding: 10px; margin: 10px 0;">
        <strong>{{ $match->home_team }} vs {{ $match->away_team }}</strong><br>
        Status: {{ ucfirst($match->status) }}<br>
        Start: {{ $match->start_time }}<br>
        @if($match->fixture)
            League: {{ $match->fixture->name }}<br>
        @endif
        @if($match->score)
            Score: {{ $match->score->score_data['home'] ?? 0 }} - {{ $match->score->score_data['away'] ?? 0 }}
        @endif
    </div>
@empty
    <p>No live matches available for {{ $slug }}.</p>
@endforelse

{{ $matches->links() }}
