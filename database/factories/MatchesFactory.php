<?php



// database/factories/MatchesFactory.php
namespace Database\Factories;

use App\Models\Sport;
use App\Models\Team;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\Venue;
use App\Models\Matches;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatchesFactory extends Factory
{
    protected $model = Matches::class;

    public function definition()
    {
        $sport = Sport::factory()->create();
        $isTeamSport = $this->faker->boolean;

        return [
            'sport_id' => $sport->id,
            'tournament_id' => Tournament::factory(),
            'team1_id' => $isTeamSport ? Team::factory()->create(['sport_id' => $sport->id]) : null,
            'team2_id' => $isTeamSport ? Team::factory()->create(['sport_id' => $sport->id]) : null,
            'player1_id' => !$isTeamSport ? Player::factory()->create(['sport_id' => $sport->id]) : null,
            'player2_id' => !$isTeamSport ? Player::factory()->create(['sport_id' => $sport->id]) : null,
            'venue_id' => Venue::factory(),
            'start_time' => $this->faker->dateTimeThisYear,
            'status' => $this->faker->randomElement(['Scheduled', 'Live', 'Completed']),
            'result' => $this->faker->sentence,
            'winner_id' => $isTeamSport && $this->faker->boolean ? Team::factory()->create(['sport_id' => $sport->id]) : null,
        ];
    }
}