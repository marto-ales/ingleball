<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_teams', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('team');
        });

        foreach (DB::table('match_teams')->select('match_id')->distinct()->pluck('match_id') as $matchId) {
            foreach (['A', 'B'] as $team) {
                $rows = DB::table('match_teams')
                    ->where('match_id', $matchId)
                    ->where('team', $team)
                    ->orderBy('id')
                    ->pluck('id');

                foreach ($rows as $position => $id) {
                    DB::table('match_teams')->where('id', $id)->update(['position' => $position]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('match_teams', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
