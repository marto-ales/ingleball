<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The per-match rating now carries a single general score: players and
     * organizers rate each other after every match. It accumulates one row per
     * (rater, rated, match). The old per-attribute columns are dropped.
     */
    public function up(): void
    {
        Schema::dropIfExists('ratings');

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rater_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rated_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rated_guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->foreignId('match_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('overall');
            $table->timestamps();

            $table->unique(['rater_user_id', 'rated_user_id', 'match_id']);
            $table->unique(['rater_user_id', 'rated_guest_id', 'match_id']);
            $table->index(['rated_user_id', 'match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rater_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rated_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rated_guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->foreignId('match_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('speed');
            $table->unsignedTinyInteger('skill');
            $table->unsignedTinyInteger('passing');
            $table->unsignedTinyInteger('shooting');
            $table->unsignedTinyInteger('defense');
            $table->unsignedTinyInteger('overall');
            $table->unsignedTinyInteger('goalkeeping')->nullable();
            $table->timestamps();

            $table->index(['rater_user_id', 'rated_user_id']);
            $table->index(['rater_user_id', 'rated_guest_id']);
        });
    }
};
