<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    protected $fillable = ['match_id', 'score_data', 'metadata'];

    protected $casts = [
        'score_data' => 'array', // Cast JSON score_data to array
        'metadata' => 'array',   // Cast JSON metadata to array
    ];

    /**
     * Get the match that this score belongs to.
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class);
    }
}