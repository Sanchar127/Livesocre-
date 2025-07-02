<?php

// app/Models/Region.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Region extends Model
{  
    protected $fillable = ['name', 'type'];

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function venues()
    {
        return $this->hasMany(Venue::class);
    }
}

