<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Games needed to win a set in a recorded match: 6 (normal set) or 8 (pro set, first to 8)
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tennis_matches', 'games_per_set')) {
            Schema::table('tennis_matches', function (Blueprint $table) {
                $table->unsignedTinyInteger('games_per_set')->default(6)->after('best_of');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tennis_matches', 'games_per_set')) {
            Schema::table('tennis_matches', function (Blueprint $table) {
                $table->dropColumn('games_per_set');
            });
        }
    }
};