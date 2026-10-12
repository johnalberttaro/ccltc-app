<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fair "Most Wins" ranking:
// - ranking_periods: the club's award / ranking period (dates + how many official games each player needs)
// - tennis_matches.is_ranking_game: whether a recorded game counts as an OFFICIAL ranking game
//   (every game is still stored and still counts in the player's overall record)
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ranking_periods')) {
            Schema::create('ranking_periods', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->date('start_date');
                $table->date('end_date');
                $table->unsignedSmallInteger('games_required')->default(5);
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('tennis_matches', 'is_ranking_game')) {
            Schema::table('tennis_matches', function (Blueprint $table) {
                // Games already recorded were normal club games, so they count unless the admin unticks them
                $table->boolean('is_ranking_game')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tennis_matches', 'is_ranking_game')) {
            Schema::table('tennis_matches', function (Blueprint $table) {
                $table->dropColumn('is_ranking_game');
            });
        }

        Schema::dropIfExists('ranking_periods');
    }
};