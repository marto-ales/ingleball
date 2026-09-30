<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_results', function (Blueprint $table) {
            $table->string('winner')->nullable()->after('match_id');
            $table->unsignedTinyInteger('diff')->nullable()->default(0)->after('winner');
        });

        $results = DB::table('match_results')->select(['id', 'team_a_score', 'team_b_score'])->get();

        foreach ($results as $result) {
            $a = (int) $result->team_a_score;
            $b = (int) $result->team_b_score;

            DB::table('match_results')->where('id', $result->id)->update([
                'winner' => $a === $b ? null : ($a > $b ? 'A' : 'B'),
                'diff' => abs($a - $b),
            ]);
        }

        Schema::table('match_results', function (Blueprint $table) {
            $table->dropColumn(['team_a_score', 'team_b_score']);
        });
    }

    public function down(): void
    {
        Schema::table('match_results', function (Blueprint $table) {
            $table->unsignedTinyInteger('team_a_score')->default(0)->after('match_id');
            $table->unsignedTinyInteger('team_b_score')->default(0)->after('team_a_score');
        });

        $results = DB::table('match_results')->select(['id', 'winner', 'diff'])->get();

        foreach ($results as $result) {
            DB::table('match_results')->where('id', $result->id)->update([
                'team_a_score' => $result->winner === 'A' ? (int) $result->diff : 0,
                'team_b_score' => $result->winner === 'B' ? (int) $result->diff : 0,
            ]);
        }

        Schema::table('match_results', function (Blueprint $table) {
            $table->dropColumn(['winner', 'diff']);
        });
    }
};
