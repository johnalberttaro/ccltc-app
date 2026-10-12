<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Optional date/time and place of the meeting or event an announcement is about
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'event_at')) {
                $table->dateTime('event_at')->nullable()->after('body');
            }
            if (! Schema::hasColumn('announcements', 'event_place')) {
                $table->string('event_place', 150)->nullable()->after('event_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            foreach (['event_place', 'event_at'] as $column) {
                if (Schema::hasColumn('announcements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};