<?php
// database/factories/PlayerPerformanceFactory.php
namespace Database\Factories;

use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerPerformance;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerPerformanceFactory extends Factory
{
    protected $model = PlayerPerformance::class;

    public function definition()
    {
        return [
            'match_id' => Matches::factory(),
            'player_id' => Player::factory(),
            'stats' => [
                'goals' => $this->faker->numberBetween(0, 5),
                'assists' => $this->faker->numberBetween(0, 3),
                'points' => $this->faker->numberBetween(0, 20),
            ],
        ];
    }
}
