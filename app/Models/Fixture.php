<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fixture extends Model
{
    protected $fillable = [
        'sport_id',
        'external_id',
        'name',
        'country',
        'season',
        'league_external_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array', // Cast JSON metadata to array
    ];

    /**
     * Get the sport that this fixture belongs to.
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Get all matches for this fixture.
     */
    public function matches(): HasMany
    {
        return $this->hasMany(Matches::class);
    }
}