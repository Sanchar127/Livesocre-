<?php

// app/Models/Tournament.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    protected $fillable = ['sport_id', 'name', 'start_date', 'end_date', 'type'];

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function matches()
    {
        return $this->hasMany(Matches::class);
    }

    public function standings()
    {
        return $this->hasMany(Standing::class);
    }
}
