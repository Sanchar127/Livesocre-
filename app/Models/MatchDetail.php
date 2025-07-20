<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchDetail extends Model
{
    protected $table = 'match_details';

    protected $fillable = [
        'match_id',
        'squad',
        'additional_info',
    ];

    protected $casts = [
        'squad' => 'array',
        'additional_info' => 'array',
    ];

    public function match()
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }
}
