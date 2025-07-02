<?php

// database/factories/SportFactory.php
namespace Database\Factories;

use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;

class SportFactory extends Factory
{
    protected $model = Sport::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->randomElement(['Football', 'Basketball', 'Tennis', 'Cricket', 'Volleyball']),
        ];
    }
}
