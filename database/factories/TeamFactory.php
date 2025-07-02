<?php

// database/factories/TeamFactory.php
namespace Database\Factories;

use App\Models\Sport;
use App\Models\Region;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition()
    {
        return [
            'sport_id' => Sport::factory(),
            'region_id' => Region::factory(),
            'name' => $this->faker->company,
            'short_name' => $this->faker->lexify('???'),
            'flag_url' => $this->faker->imageUrl(),
        ];
    }
}