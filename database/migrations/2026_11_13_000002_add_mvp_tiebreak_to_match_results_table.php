<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the stored MVP was chosen by the organizer to break a tie. Only
     * that pick survives a still-tied vote; an automatic leader does not.
     */
    public function up(): void
    {
        Schema::table('match_results', function (Blueprint $table) {
            $table->boolean('mvp_tiebreak')->default(false)->after('mvp_guest_id');
        });
    }

    public function down(): void
    {
        Schema::table('match_results', function (Blueprint $table) {
            $table->dropColumn('mvp_tiebreak');
        });
    }
};
