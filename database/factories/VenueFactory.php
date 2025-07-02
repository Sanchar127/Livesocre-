<?php


// database/factories/VenueFactory.php
namespace Database\Factories;

use App\Models\Region;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition()
    {
        return [
            'region_id' => Region::factory(),
            'name' => $this->faker->company . ' Stadium',
            'city' => $this->faker->city,
            'country' => $this->faker->country,
            'capacity' => $this->faker->numberBetween(1000, 100000),
        ];
    }
}