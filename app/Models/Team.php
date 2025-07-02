<?php

// app/Models/Team.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = ['sport_id', 'region_id', 'name', 'short_name', 'flag_url'];

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function players()
    {
        return $this->hasMany(Player::class);
    }

    public function homeMatches()
    {
        return $this->hasMany(Matches::class, 'team1_id');
    }

    public function awayMatches()
    {
        return $this->hasMany(Matches::class, 'team2_id');
    }

    public function wonMatches()
    {
        return $this->hasMany(Matches::class, 'winner_id');
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
