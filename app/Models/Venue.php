<?php

// app/Models/Venue.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    protected $fillable = ['region_id', 'name', 'city', 'country', 'capacity'];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function matches()
    {
        return $this->hasMany(Matches::class);
    }
}

