<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organizer evaluation of a player's whole profile. One row per
     * (organizer, player) that organizers can edit at any time: it never
     * accumulates history.
     */
    public function up(): void
    {
        Schema::create('player_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rated_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('speed');
            $table->unsignedTinyInteger('skill');
            $table->unsignedTinyInteger('passing');
            $table->unsignedTinyInteger('shooting');
            $table->unsignedTinyInteger('defense');
            $table->unsignedTinyInteger('goalkeeping');
            $table->timestamps();

            $table->unique(['organizer_user_id', 'rated_user_id']);
            $table->index('rated_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_evaluations');
    }
};
