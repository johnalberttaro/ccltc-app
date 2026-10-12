<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bracket settings on each tournament
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('bracket_format', 30)->default('single_elimination');
            $table->unsignedTinyInteger('best_of')->default(1);
            $table->dateTime('bracket_generated_at')->nullable();
        });

        // Teams / players entered in a tournament (names typed by the admin)
        Schema::create('tournament_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('seed')->nullable();
            $table->timestamps();
        });

        // Every match in the bracket (or round robin schedule)
        Schema::create('bracket_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round');
            $table->unsignedSmallInteger('position');
            $table->foreignId('entry1_id')->nullable()->constrained('tournament_entries')->nullOnDelete();
            $table->foreignId('entry2_id')->nullable()->constrained('tournament_entries')->nullOnDelete();
            $table->foreignId('winner_entry_id')->nullable()->constrained('tournament_entries')->nullOnDelete();
            $table->json('score')->nullable();
            $table->boolean('is_bye')->default(false);
            $table->boolean('is_walkover')->default(false);
            $table->unsignedBigInteger('next_match_id')->nullable();
            $table->unsignedTinyInteger('next_slot')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bracket_matches');
        Schema::dropIfExists('tournament_entries');

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['bracket_format', 'best_of', 'bracket_generated_at']);
        });
    }
};