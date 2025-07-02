<?php

// database/factories/MatchScoreFactory.php
namespace Database\Factories;

use App\Models\Matches;
use App\Models\Team;
use App\Models\Player;
use App\Models\MatchScore;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatchScoreFactory extends Factory
{
    protected $model = MatchScore::class;

    public function definition()
    {
        $match = Matches::factory()->create();
        $isTeamSport = $match->team1_id !== null;

        return [
            'match_id' => $match->id,
            'team_id' => $isTeamSport ? ($this->faker->boolean ? $match->team1_id : $match->team2_id) : null,
            'player_id' => !$isTeamSport ? ($this->faker->boolean ? $match->player1_id : $match->player2_id) : null,
            'score' => $this->faker->numberBetween(0, 10) . '-' . $this->faker->numberBetween(0, 10),
            'period' => $this->faker->randomElement(['1st Half', '2nd Half', 'Set 1', 'Quarter 1']),
        ];
    }
}