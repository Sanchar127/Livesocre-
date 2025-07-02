<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// database/migrations/2025_07_02_000005_create_tournaments_table.php
class CreateTournamentsTable extends Migration
{
    public function up()
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->onDelete('restrict');
            $table->string('name', 100);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('type', 50)->nullable();
            $table->timestamps();
            $table->unique(['sport_id', 'name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tournaments');
    }
}

