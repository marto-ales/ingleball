<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->dateTime('played_at');
            $table->string('venue')->nullable();
            $table->unsignedTinyInteger('size')->default(5);
            $table->string('status')->default('open'); // open | locked | finished
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('locked_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'played_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
