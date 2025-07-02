<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// database/migrations/2025_07_02_000003_create_teams_table.php
class CreateTeamsTable extends Migration
{
    public function up()
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->onDelete('restrict');
            $table->foreignId('region_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name', 100);
            $table->string('short_name', 20)->nullable();
            $table->string('flag_url', 255)->nullable();
            $table->timestamps();
            $table->unique(['sport_id', 'name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('teams');
    }
}
