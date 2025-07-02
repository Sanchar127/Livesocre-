<?php

// database/factories/StandingFactory.php
namespace Database\Factories;

use App\Models\Tournament;
use App\Models\Team;
use App\Models\Player;
use App\Models\Standing;
use Illuminate\Database\Eloquent\Factories\Factory;

class StandingFactory extends Factory
{
    protected $model = Standing::class;

    public function definition()
    {
        $isTeamSport = $this->faker->boolean;

        return [
            'tournament_id' => Tournament::factory(),
            'team_id' => $isTeamSport ? Team::factory() : null,
            'player_id' => !$isTeamSport ? Player::factory() : null,
            'matches_played' => $this->faker->numberBetween(0, 20),
            'wins' => $this->faker->numberBetween(0, 10),
            'losses' => $this->faker->numberBetween(0, 10),
            'ties' => $this->faker->numberBetween(0, 5),
            'points' => $this->faker->numberBetween(0, 30),
        ];
    }
}