<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->boolean('likes_goalie')->default(false)->after('overall');
            $table->unsignedTinyInteger('goalkeeping')->default(5)->after('likes_goalie');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['goalkeeping', 'likes_goalie']);
        });
    }
};
