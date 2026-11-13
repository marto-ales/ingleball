<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One MVP vote per attendee and match. The voter is always an account;
     * the voted player can be a user or a guest.
     */
    public function up(): void
    {
        Schema::create('mvp_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voter_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('voted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voted_guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->timestamps();

            $table->unique(['match_id', 'voter_user_id']);
            $table->index(['match_id', 'voted_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mvp_votes');
    }
};
