<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sport extends Model
{
    protected $fillable = ['name', 'slug', 'metadata'];

    protected $casts = [
        'metadata' => 'array', // Cast JSON metadata to array for easy access
    ];

    /**
     * Get all fixtures for this sport.
     */
    public function fixtures(): HasMany
    {
        return $this->hasMany(Fixture::class);
    }

    /**
     * Get all matches for this sport.
     */
    public function matches(): HasMany
    {
        return $this->hasMany(Matches::class);
    }
}