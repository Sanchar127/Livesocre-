<?php

// database/factories/TournamentFactory.php
namespace Database\Factories;

use App\Models\Sport;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    public function definition()
    {
        return [
            'sport_id' => Sport::factory(),
            'name' => $this->faker->sentence(3),
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->date(),
            'type' => $this->faker->randomElement(['League', 'Knockout', 'Round Robin']),
        ];
    }
}
