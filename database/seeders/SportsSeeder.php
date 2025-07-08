<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class SportsSeeder extends Seeder
{
   

public function run()
{
    // Football
    $footballId = DB::table('sports')->insertGetId(['name' => 'Football']);
    DB::table('score_fields')->insert([
        ['sport_id' => $footballId, 'field_name' => 'goals', 'field_type' => 'integer'],
    ]);

    // Cricket
    $cricketId = DB::table('sports')->insertGetId(['name' => 'Cricket']);
    DB::table('score_fields')->insert([
        ['sport_id' => $cricketId, 'field_name' => 'runs', 'field_type' => 'integer'],
        ['sport_id' => $cricketId, 'field_name' => 'wickets', 'field_type' => 'integer'],
        ['sport_id' => $cricketId, 'field_name' => 'overs', 'field_type' => 'float'],
    ]);

    // Tennis
    $tennisId = DB::table('sports')->insertGetId(['name' => 'Tennis']);
    DB::table('score_fields')->insert([
        ['sport_id' => $tennisId, 'field_name' => 'sets', 'field_type' => 'array'],
    ]);
}

}
