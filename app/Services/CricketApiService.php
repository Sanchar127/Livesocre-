<?php 
// app/Services/CricketApiService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class CricketApiService {
    public function fetchMatches() {
        $response = Http::get("https://api.cricapi.com/v1/currentMatches?apikey=5b5c582d-bb03-4243-a539-028aa954ecf7");
        return $response->json()['data'] ?? [];
    }
}
