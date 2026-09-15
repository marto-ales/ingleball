<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role')->default('going'); // going | substitute | out
            $table->unsignedInteger('list_order')->default(0);
            $table->timestamps();

            $table->unique(['match_id', 'user_id']);
            $table->unique(['match_id', 'guest_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_entries');
    }
};
