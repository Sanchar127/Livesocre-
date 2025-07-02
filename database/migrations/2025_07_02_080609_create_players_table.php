<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// database/migrations/2025_07_02_000004_create_players_table.php
class CreatePlayersTable extends Migration
{
    public function up()
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('sport_id')->constrained()->onDelete('restrict');
            $table->string('name', 100);
            $table->string('role', 50)->nullable();
            $table->timestamps();
            $table->unique(['sport_id', 'name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('players');
    }
}

