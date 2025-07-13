<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sports', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Already indexed with unique
            $table->string('slug')->unique(); // Already indexed with unique
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->onDelete('cascade');
            $table->string('external_id')->unique();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('season')->nullable();
            $table->string('league_external_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // ✅ Indexes for filtering/sorting
            $table->index(['sport_id']);
            $table->index(['name', 'country']); // Often queried together
        });

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

            // ✅ Indexes for filtering/sorting/searching
            $table->index(['sport_id']);
            $table->index(['fixture_id']);
            $table->index(['status']);
            $table->index(['start_time']);
            $table->index(['home_team', 'away_team']);
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->onDelete('cascade');
            $table->json('score_data');
            $table->json('metadata')->nullable();
            $table->timestamps();

            // ✅ Index for joining
            $table->index(['match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('fixtures');
        Schema::dropIfExists('sports');
    }
};
