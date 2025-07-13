<!DOCTYPE html>
<html>
<head>
    <title>{{ ucfirst($slug) }} Matches</title>
</head>
<body>

<h1>{{ ucfirst($slug) }} Matches</h1>

<!-- Filter links -->
<nav>
    <a href="{{ route('matches.index', ['sport' => 'soccer']) }}">Football</a> |
    <a href="{{ route('matches.index', ['sport' => 'cricket']) }}">Cricket</a>
</nav>

<hr>

<!-- Match List -->
@forelse($matches as $match)
    <div>
        <strong>{{ $match->home_team }} vs {{ $match->away_team }}</strong><br>
        Status: {{ $match->status }} <br>
        Start Time: {{ $match->start_time }}
    </div>
    <hr>
@empty
    <p>No {{ $slug }} matches available.</p>
@endforelse

<!-- Pagination -->
{{ $matches->withQueryString()->links() }}

</body>
</html>
