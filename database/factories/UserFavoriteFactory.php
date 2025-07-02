<?php

// database/factories/CommentaryFactory.php
namespace Database\Factories;

use App\Models\Matches;
use App\Models\Commentary;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentaryFactory extends Factory
{
    protected $model = Commentary::class;

    public function definition()
    {
        return [
            'match_id' => Matches::factory(),
            'commentary_text' => $this->faker->sentence,
            'period' => $this->faker->randomElement(['1st Half', '2nd Half', 'Set 1', 'Quarter 1']),
            'timestamp' => $this->faker->dateTimeThisYear,
        ];
    }
}