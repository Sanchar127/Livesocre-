<?php

// app/Models/Commentary.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commentary extends Model
{
    protected $fillable = ['match_id', 'commentary_text', 'period', 'timestamp'];

    public function match()
    {
        return $this->belongsTo(Matches::class);
    }
}
