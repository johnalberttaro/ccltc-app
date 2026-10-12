<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Members vs. other names typed in Record Match (guests, nicknames).
// Everyone already in the list stays a member.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('players', 'is_member')) {
            Schema::table('players', function (Blueprint $table) {
                $table->boolean('is_member')->default(true)->after('last_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('players', 'is_member')) {
            Schema::table('players', function (Blueprint $table) {
                $table->dropColumn('is_member');
            });
        }
    }
};