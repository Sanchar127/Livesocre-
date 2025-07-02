<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// database/migrations/2025_07_02_000014_create_commentary_table.php
class CreateCommentaryTable extends Migration
{
    public function up()
    {
        Schema::create('commentary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->onDelete('cascade');
            $table->text('commentary_text');
            $table->string('period', 50)->nullable();
            $table->dateTime('timestamp');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('commentary');
    }
}
