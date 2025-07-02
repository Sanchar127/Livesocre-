<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sport;
use App\Models\Region;
use App\Models\Team;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\Venue;
use App\Models\Matches;
use App\Models\MatchScore;
use App\Models\PlayerPerformance;
use App\Models\Commentary;
use App\Models\Standing;
use App\Models\News;
use App\Models\User;
use App\Models\UserFavorite;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Clear the sports table first
        Sport::truncate();

        // Seed data
        Sport::factory()->count(10)->create();
        Region::factory(10)->create();
        Team::factory(20)->create();
        Player::factory(50)->create();
        Tournament::factory(5)->create();
        Venue::factory(10)->create();
        Matches::factory(20)->create()->each(function ($match) {
            MatchScore::factory(2)->create(['match_id' => $match->id]);
            PlayerPerformance::factory(2)->create(['match_id' => $match->id]);
            Commentary::factory(5)->create(['match_id' => $match->id]);
        });
        Standing::factory(10)->create();
        News::factory(15)->create();
        User::factory(10)->create()->each(function ($user) {
            UserFavorite::factory(2)->create(['user_id' => $user->id]);
        });
    }
}
