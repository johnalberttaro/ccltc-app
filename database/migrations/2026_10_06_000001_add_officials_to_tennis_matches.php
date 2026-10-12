<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Names of the referee and the ball boy(s) of a recorded match (both optional, plain text)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tennis_matches', function (Blueprint $table) {
            if (! Schema::hasColumn('tennis_matches', 'referee')) {
                $table->string('referee', 100)->nullable();
            }
            if (! Schema::hasColumn('tennis_matches', 'ball_boy')) {
                $table->string('ball_boy', 150)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tennis_matches', function (Blueprint $table) {
            foreach (['referee', 'ball_boy'] as $column) {
                if (Schema::hasColumn('tennis_matches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};