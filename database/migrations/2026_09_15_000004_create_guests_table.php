<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->unsignedTinyInteger('speed')->nullable();
            $table->unsignedTinyInteger('skill')->nullable();
            $table->unsignedTinyInteger('passing')->nullable();
            $table->unsignedTinyInteger('shooting')->nullable();
            $table->unsignedTinyInteger('defense')->nullable();
            $table->unsignedTinyInteger('overall')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
