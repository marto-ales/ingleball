<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('team_a_score')->default(0);
            $table->unsignedTinyInteger('team_b_score')->default(0);
            $table->foreignId('mvp_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mvp_guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_results');
    }
};
