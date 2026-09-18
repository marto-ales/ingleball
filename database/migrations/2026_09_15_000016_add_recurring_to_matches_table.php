<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->boolean('recurring')->default(false);
            $table->foreignId('recurring_id')->nullable()->constrained('matches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['recurring_id']);
            $table->dropColumn(['recurring_id', 'recurring']);
        });
    }
};
