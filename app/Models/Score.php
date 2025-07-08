<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    protected $fillable = ['match_id', 'score_data'];

    protected $casts = [
        'score_data' => 'array',
    ];

    /**
     * Get the match that this score belongs to.
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class);
    }
}