<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tennis_matches', function (Blueprint $table) {
            $table->id();
            $table->date('match_date')->index();
            $table->time('start_time')->nullable();
            $table->string('venue')->nullable();
            $table->enum('match_type', ['singles', 'doubles'])->default('singles');
            $table->unsignedTinyInteger('best_of')->default(3);
            $table->enum('status', ['scheduled', 'completed', 'retired', 'walkover'])->default('completed');
            $table->unsignedTinyInteger('winner_side')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tennis_matches');
    }
};