<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every list, leaderboard and lookup filters by group, so each group_id gets
 * an index. The evaluation uniqueness also includes the group: the same
 * organizer can evaluate the same player again once the player belongs to
 * another group.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = ['users', 'matches', 'guests', 'players', 'ratings', 'player_evaluations'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->index('group_id');
            });
        }

        Schema::table('player_evaluations', function (Blueprint $blueprint): void {
            $blueprint->dropUnique(['organizer_user_id', 'rated_user_id']);
            $blueprint->unique(['organizer_user_id', 'rated_user_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::table('player_evaluations', function (Blueprint $blueprint): void {
            $blueprint->dropUnique(['organizer_user_id', 'rated_user_id', 'group_id']);
            $blueprint->unique(['organizer_user_id', 'rated_user_id']);
        });

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropIndex(['group_id']);
            });
        }
    }
};
