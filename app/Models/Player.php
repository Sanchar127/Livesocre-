<?php


// app/Models/Player.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = ['team_id', 'sport_id', 'name', 'role'];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function matchesAsPlayer1()
    {
        return $this->hasMany(Match::class, 'player1_id');
    }

    public function matchesAsPlayer2()
    {
        return $this->hasMany(Match::class, 'player2_id');
    }

    public function performances()
    {
        return $this->hasMany(PlayerPerformance::class);
    }

    public function scores()
    {
        return $this->hasMany(MatchScore::class);
    }

    public function standings()
    {
        return $this->hasMany(Standing::class);
    }

    public function news()
    {
        return $this->hasMany(News::class);
    }

    public function favorites()
    {
        return $this->hasMany(UserFavorite::class);
    }
}

