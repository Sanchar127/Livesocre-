<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate child tables first
        DB::table('players')->truncate();
        DB::table('teams')->truncate();
        DB::table('venues')->truncate();
        DB::table('tournaments')->truncate();
        DB::table('matches')->truncate();
        DB::table('match_scores')->truncate();

        // Then truncate parent tables
        DB::table('regions')->truncate();
        DB::table('sports')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Call individual seeders
        $this->call([
            SportSeeder::class,
            RegionSeeder::class,
            TeamSeeder::class,
            PlayerSeeder::class,
            TournamentSeeder::class,
            VenueSeeder::class,
            MatchSeeder::class,
            MatchScoreSeeder::class,
        ]);
    }
}
