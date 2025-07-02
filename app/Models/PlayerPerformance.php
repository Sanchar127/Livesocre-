<?php

// app/Models/PlayerPerformance.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerPerformance extends Model
{
    protected $fillable = ['match_id', 'player_id', 'stats'];

    protected $casts = [
        'stats' => 'array',
    ];

    public function match()
    {
        return $this->belongsTo(Matches::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }
}
