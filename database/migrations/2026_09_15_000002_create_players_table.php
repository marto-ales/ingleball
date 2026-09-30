<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('speed')->default(5);
            $table->unsignedTinyInteger('skill')->default(5);
            $table->unsignedTinyInteger('passing')->default(5);
            $table->unsignedTinyInteger('shooting')->default(5);
            $table->unsignedTinyInteger('defense')->default(5);
            $table->unsignedTinyInteger('overall')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
