<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Remove the earlier simple bracket tables (test data only)
        Schema::dropIfExists('bracket_matches');
        Schema::dropIfExists('tournament_entries');

        foreach (['bracket_format', 'bracket_generated_at'] as $old) {
            if (Schema::hasColumn('tournaments', $old)) {
                Schema::table('tournaments', fn (Blueprint $table) => $table->dropColumn($old));
            }
        }

        // 2) Status gets more options (draft, registration open/closed, cancelled)
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('status', 30)->default('upcoming')->change();
        });

        // 3) Format, group and scoring settings
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('format', 30)->default('group_knockout')->after('match_type');
            $table->string('court')->nullable()->after('venue');
            $table->unsignedSmallInteger('max_participants')->nullable();

            // Group stage
            $table->unsignedTinyInteger('group_count')->default(4);
            $table->unsignedTinyInteger('group_size')->default(4);
            $table->unsignedTinyInteger('advance_per_group')->default(2);
            $table->string('knockout_format', 20)->default('single');   // single | double
            $table->boolean('grand_final_reset')->default(true);       // double elimination only

            // Match rules
            if (! Schema::hasColumn('tournaments', 'best_of')) {
                $table->unsignedTinyInteger('best_of')->default(1);
            }
            $table->unsignedTinyInteger('games_per_set')->default(6);
            $table->string('final_set', 20)->default('full');          // full | match_tiebreak

            // Standings rules
            $table->unsignedTinyInteger('points_win')->default(3);
            $table->unsignedTinyInteger('points_loss')->default(0);
            $table->string('standings_method', 20)->default('difference'); // difference | percentage
            $table->boolean('use_head_to_head')->default(true);

            // Progress
            $table->dateTime('groups_generated_at')->nullable();
            $table->dateTime('schedule_generated_at')->nullable();
            $table->dateTime('knockout_generated_at')->nullable();
        });

        // 4) Groups
        Schema::create('tournament_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name', 20);                     // A, B, C...
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['tournament_id', 'name']);
        });

        // 5) Participants: typed names only (doubles = "Juan / Pedro") + optional seed
        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('seed')->nullable();
            $table->foreignId('group_id')->nullable()->constrained('tournament_groups')->nullOnDelete();
            $table->unsignedTinyInteger('group_position')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'name']);
        });

        // 6) Matches (group stage, round robin, and every bracket match)
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 20);                    // group | winners | losers | final
            $table->foreignId('group_id')->nullable()->constrained('tournament_groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->unsignedSmallInteger('match_number')->default(1);   // order within its group / round
            $table->string('code', 20)->nullable();                     // e.g. A-3, QF1, SF2, LB R2-1, GF
            $table->foreignId('participant1_id')->nullable()->constrained('tournament_participants')->nullOnDelete();
            $table->foreignId('participant2_id')->nullable()->constrained('tournament_participants')->nullOnDelete();
            $table->string('participant1_source', 60)->nullable();      // "Group A #1", "Winner QF1"
            $table->string('participant2_source', 60)->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('tournament_participants')->nullOnDelete();
            $table->string('status', 20)->default('scheduled');         // scheduled | in_progress | completed | cancelled
            $table->boolean('is_bye')->default(false);
            $table->boolean('is_walkover')->default(false);
            $table->unsignedBigInteger('next_match_id')->nullable();
            $table->unsignedTinyInteger('next_slot')->nullable();
            $table->unsignedBigInteger('loser_next_match_id')->nullable();  // double elimination
            $table->unsignedTinyInteger('loser_next_slot')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('court', 60)->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'stage', 'round']);
        });

        // 7) One row per set
        Schema::create('tournament_match_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_match_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->unsignedTinyInteger('participant1_games');
            $table->unsignedTinyInteger('participant2_games');
            $table->unsignedTinyInteger('participant1_tiebreak')->nullable();
            $table->unsignedTinyInteger('participant2_tiebreak')->nullable();
            $table->boolean('is_match_tiebreak')->default(false);       // deciding set played as a 10-point tiebreak
            $table->timestamps();

            $table->unique(['tournament_match_id', 'set_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_match_sets');
        Schema::dropIfExists('tournament_matches');
        Schema::dropIfExists('tournament_participants');
        Schema::dropIfExists('tournament_groups');

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn([
                'format', 'court', 'max_participants', 'group_count', 'group_size', 'advance_per_group',
                'knockout_format', 'grand_final_reset', 'games_per_set', 'final_set',
                'points_win', 'points_loss', 'standings_method', 'use_head_to_head',
                'groups_generated_at', 'schedule_generated_at', 'knockout_generated_at',
            ]);
        });
    }
};