<?php

// app/Models/Match.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Matches extends Model
{
    protected $fillable = [
        'sport_id', 'tournament_id', 'team1_id', 'team2_id', 'player1_id', 'player2_id',
        'venue_id', 'start_time', 'status', 'winner_id', 'result'
    ];

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function team1()
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2()
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    public function player1()
    {
        return $this->belongsTo(Player::class, 'player1_id');
    }

    public function player2()
    {
        return $this->belongsTo(Player::class, 'player2_id');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function winner()
    {
        return $this->belongsTo(Team::class, 'winner_id');
    }

    public function scores()
    {
        return $this->hasMany(MatchScore::class);
    }

    public function performances()
    {
        return $this->hasMany(PlayerPerformance::class);
    }

    public function commentary()
    {
        return $this->hasMany(Commentary::class);
    }

    public function news()
    {
        return $this->hasMany(News::class);
    }
}
