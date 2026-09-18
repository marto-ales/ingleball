<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('players')
            ->whereNull('self_eval_completed_at')
            ->where(function ($query) {
                $query->where('speed', '<>', 5)
                    ->orWhere('skill', '<>', 5)
                    ->orWhere('passing', '<>', 5)
                    ->orWhere('shooting', '<>', 5)
                    ->orWhere('defense', '<>', 5)
                    ->orWhere('goalkeeping', '<>', 5)
                    ->orWhere('likes_goalie', true);
            })
            ->update(['self_eval_completed_at' => now()->subDay()]);
    }

    public function down(): void
    {
        DB::table('players')
            ->where(function ($query) {
                $query->where('speed', '=', 5)
                    ->where('skill', '=', 5)
                    ->where('passing', '=', 5)
                    ->where('shooting', '=', 5)
                    ->where('defense', '=', 5)
                    ->where('goalkeeping', '=', 5)
                    ->where('likes_goalie', false);
            })
            ->update(['self_eval_completed_at' => null]);
    }
};
