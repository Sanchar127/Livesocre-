<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; 

class CreateMatchesTable extends Migration
{
  public function up()
{
    Schema::create('matches', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sport_id')->constrained()->onDelete('restrict');
        $table->foreignId('tournament_id')->nullable()->constrained()->onDelete('set null');
        $table->foreignId('team1_id')->nullable()->constrained('teams')->onDelete('set null');
        $table->foreignId('team2_id')->nullable()->constrained('teams')->onDelete('set null');
        $table->foreignId('player1_id')->nullable()->constrained('players')->onDelete('set null');
        $table->foreignId('player2_id')->nullable()->constrained('players')->onDelete('set null');
        $table->foreignId('venue_id')->nullable()->constrained()->onDelete('set null');
        $table->foreignId('winner_id')->nullable()->constrained('teams')->onDelete('set null');
        $table->dateTime('start_time')->nullable();
        $table->string('status', 20);
        $table->string('result', 255)->nullable();
        $table->timestamps();
    });

    // ❌ Removed the CHECK constraint that caused the error
}


    public function down()
    {
        Schema::dropIfExists('matches');
    }
}
