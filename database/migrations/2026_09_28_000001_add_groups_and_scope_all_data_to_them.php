<?php

use App\Models\Group;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds multi-group support: every user, match, guest, player profile,
 * rating, evaluation and setting belongs to exactly one group. The existing
 * data is moved to a single default group ("Ingleball") so nothing is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('join_code')->unique();
            $table->string('whatsapp_group')->nullable();
            $table->timestamps();
        });

        $defaultGroupId = DB::table('groups')->insertGetId([
            'name' => 'Ingleball',
            'join_code' => Group::randomJoinCode(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Settings become per-group: a plain id PK plus a unique (group_id, key).
        $rows = DB::table('settings')->get();

        Schema::drop('settings');
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['group_id', 'key']);
        });

        foreach (['users', 'matches', 'guests', 'players', 'ratings', 'player_evaluations'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            });
        }

        // Backfill: the default group adopts every existing row.
        $whatsapp = DB::table('users')->whereNotNull('whatsapp_group')->value('whatsapp_group');

        if ($whatsapp !== null) {
            DB::table('groups')->where('id', $defaultGroupId)->update(['whatsapp_group' => $whatsapp]);
        }

        foreach (['users', 'matches', 'guests', 'players', 'ratings', 'player_evaluations'] as $table) {
            DB::table($table)->whereNull('group_id')->update(['group_id' => $defaultGroupId]);
        }

        foreach ($rows as $row) {
            DB::table('settings')->insert([
                'group_id' => $defaultGroupId,
                'key' => $row->key,
                'value' => $row->value,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        // The WhatsApp link moves from the user to the group.
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('whatsapp_group');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_group')->nullable();
        });

        $rows = DB::table('settings')->get();

        Schema::drop('settings');
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        foreach ($rows as $row) {
            DB::table('settings')->insert([
                'key' => $row->key,
                'value' => $row->value,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        foreach (['users', 'matches', 'guests', 'players', 'ratings', 'player_evaluations'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropConstrainedForeignId('group_id'));
        }

        Schema::dropIfExists('groups');
    }
};
