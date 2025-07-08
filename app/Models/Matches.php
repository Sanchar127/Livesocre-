<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Matches extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'sport_id',
        'fixture_id',
        'external_match_id',
        'home_team',
        'away_team',
        'status',
        'start_time',
        'league',
    ];

    protected $casts = [
        'start_time' => 'datetime',
    ];

    /**
     * Get the sport that this match belongs to.
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Get the fixture that this match belongs to.
     */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    /**
     * Get the score for this match.
     */
    public function score(): HasOne
    {
        return $this->hasOne(Score::class, 'match_id');
    }
}