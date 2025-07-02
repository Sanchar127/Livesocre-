<?php

// app/Models/News.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    protected $fillable = ['sport_id', 'match_id', 'team_id', 'player_id', 'title', 'content', 'published_at'];

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function match()
    {
        return $this->belongsTo(Matches::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }
}