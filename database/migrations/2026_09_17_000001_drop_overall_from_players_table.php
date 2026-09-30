<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('overall');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedTinyInteger('overall')->default(5);
        });
    }
};
