{{-- /matches/by_country/index.blade.php--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Matches in {{ $country }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    {{-- Bootstrap CSS (optional, for styling) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">Matches in {{ $country }}</h2>

        @if($matches->isEmpty())
            <div class="alert alert-warning">No matches found for this country.</div>
        @else
            <ul class="list-group">
                @foreach($matches as $match)
                    <li class="list-group-item">
                        <strong>{{ $match->home_team }} vs {{ $match->away_team }}</strong><br>
                        Sport: {{ $match->sport->name ?? 'N/A' }}<br>
                        Fixture Country: {{ $match->fixture->country ?? 'N/A' }}<br>
                        Start Time: {{ \Carbon\Carbon::parse($match->start_time)->format('d M Y, h:i A') }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Bootstrap JS (optional) --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
