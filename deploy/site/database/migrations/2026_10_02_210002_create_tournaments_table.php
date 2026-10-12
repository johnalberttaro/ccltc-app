<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();          // e.g. Open, Juniors, Seniors, Club
            $table->enum('match_type', ['singles', 'doubles', 'both'])->default('doubles');
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('venue')->nullable();
            $table->date('registration_deadline')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->text('registration_info')->nullable();
            $table->string('registration_link')->nullable();
            $table->enum('status', ['upcoming', 'ongoing', 'completed'])->default('upcoming')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};