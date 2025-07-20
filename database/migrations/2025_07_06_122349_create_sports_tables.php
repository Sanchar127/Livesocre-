<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sports table
        Schema::create('sports', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Fixtures table (removed league_external_id)
        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->onDelete('cascade');
            $table->string('external_id')->unique();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('season')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['sport_id']);
            $table->index(['name', 'country']);
        });

        // Matches table (home_team and away_team kept here)
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->string('external_match_id')->unique();
            $table->foreignId('sport_id')->constrained()->onDelete('cascade');
            $table->foreignId('fixture_id')->nullable()->constrained()->onDelete('set null');
            $table->string('home_team');
            $table->string('away_team');
            $table->string('status');
            $table->dateTime('start_time')->nullable();
            $table->string('league')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['sport_id']);
            $table->index(['fixture_id']);
            $table->index(['status']);
            $table->index(['start_time']);
            $table->index(['home_team', 'away_team']);
        });

        // Scores table
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->onDelete('cascade');
            $table->json('score_data');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['match_id']);
        });

        // Match Details table for extended info including squads
        Schema::create('match_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->json('squad')->nullable();          // JSON array of squads (players)
            $table->json('additional_info')->nullable(); // Any extra metadata
            $table->timestamps();

            $table->index(['match_id']);
        });

        // Results table
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
      
            $table->json('result_details')->nullable(); // extra info
            $table->timestamps();

            $table->index(['match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
        Schema::dropIfExists('match_details');
        Schema::dropIfExists('scores');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('fixtures');
        Schema::dropIfExists('sports');
    }
};
