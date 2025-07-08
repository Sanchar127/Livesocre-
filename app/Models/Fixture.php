<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fixture extends Model
{
    protected $table = 'fixtures';

    protected $fillable = [
        'sport_id',
        'external_id',
        'name',
        'country',
        'season',
        'league_external_id',
        'date_start',
        'date_end',
        'is_cup',
        'is_women',
        'live_lineups',
        'live_stats',
        'live_pbp',
        'path',
    ];

    /**
     * Get the sport that this fixture belongs to.
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Get the matches that belong to this fixture.
     */
    public function matches(): HasMany
    {
        return $this->hasMany(Matches::class, 'fixture_id');
    }
}