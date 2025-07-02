<?php

// database/factories/NewsFactory.php
namespace Database\Factories;

use App\Models\Sport;
use App\Models\Matches;
use App\Models\Team;
use App\Models\Player;
use App\Models\News;
use Illuminate\Database\Eloquent\Factories\Factory;

class NewsFactory extends Factory
{
    protected $model = News::class;

    public function definition()
    {
        return [
            'sport_id' => Sport::factory(),
            'match_id' => Matches::factory(),
            'team_id' => Team::factory(),
            'player_id' => Player::factory(),
            'title' => $this->faker->sentence,
            'content' => $this->faker->paragraph,
            'published_at' => $this->faker->dateTimeThisYear,
        ];
    }
}
