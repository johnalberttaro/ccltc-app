<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tennis_match_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->unsignedTinyInteger('side1_games');
            $table->unsignedTinyInteger('side2_games');
            $table->unsignedTinyInteger('side1_tiebreak')->nullable();
            $table->unsignedTinyInteger('side2_tiebreak')->nullable();
            $table->unique(['tennis_match_id', 'set_number']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_sets');
    }
};