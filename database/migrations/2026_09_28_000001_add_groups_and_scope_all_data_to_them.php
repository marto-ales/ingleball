<?php

use App\Models\Group;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds multi-group support. A user has ONE account (username, password and
 * contact data) that can belong to several groups at once through the
 * group_user table, which also carries that membership's organizer flag and
 * block. Everything else — matches, guests, player profiles, ratings,
 * evaluations and settings — belongs to exactly one group and never travels
 * between them. The existing data moves to a single default group
 * ("Ingleball") so nothing is lost.
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

        foreach (['matches', 'guests', 'players', 'ratings', 'player_evaluations'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            });
        }

        // A player profile is per group: the same account has a different row
        // (self evaluation, goalkeeper) in each group it plays in.
        if (Schema::hasIndex('players', ['user_id'], 'unique')) {
            Schema::table('players', fn (Blueprint $blueprint) => $blueprint->dropUnique(['user_id']));
        }

        if (! Schema::hasIndex('players', ['user_id', 'group_id'], 'unique')) {
            Schema::table('players', fn (Blueprint $blueprint) => $blueprint->unique(['user_id', 'group_id']));
        }

        // One account, several groups: the pivot carries the membership's
        // organizer flag and block, which used to live on the user.
        Schema::create('group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_organizer')->default(false);
            $table->timestamp('banned_at')->nullable();
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
            $table->index('user_id');
        });

        // Backfill: the default group adopts every existing row.
        $whatsapp = DB::table('users')->whereNotNull('whatsapp_group')->value('whatsapp_group');

        if ($whatsapp !== null) {
            DB::table('groups')->where('id', $defaultGroupId)->update(['whatsapp_group' => $whatsapp]);
        }

        foreach (['matches', 'guests', 'players', 'ratings', 'player_evaluations'] as $table) {
            DB::table($table)->whereNull('group_id')->update(['group_id' => $defaultGroupId]);
        }

        $now = now();
        $users = DB::table('users')->get();

        foreach ($users as $user) {
            DB::table('group_user')->insert([
                'group_id' => $defaultGroupId,
                'user_id' => $user->id,
                'is_organizer' => (bool) $user->is_organizer,
                'banned_at' => $user->banned_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
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

        // The WhatsApp link moves from the user to the group; the organizer
        // flag and the block move to the membership. The account keeps only
        // its own login and contact data.
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_group', 'is_organizer', 'banned_at']);
        });
    }

    public function down(): void
    {
        // One profile per user is coming back, so a user with a profile in
        // several groups can only keep one: the most recent.
        $players = DB::table('players')
            ->select('user_id', DB::raw('MAX(id) as keep_id'))
            ->groupBy('user_id')
            ->pluck('keep_id', 'user_id');

        DB::table('players')
            ->whereNotIn('id', $players->values()->all())
            ->delete();

        if (Schema::hasIndex('players', ['user_id', 'group_id'], 'unique')) {
            Schema::table('players', fn (Blueprint $blueprint) => $blueprint->dropUnique(['user_id', 'group_id']));
        }

        if (! Schema::hasIndex('players', ['user_id'], 'unique')) {
            Schema::table('players', fn (Blueprint $blueprint) => $blueprint->unique('user_id'));
        }

        // A database built before memberships existed has nothing to restore.
        $memberships = Schema::hasTable('group_user')
            ? DB::table('group_user')->get()
            : collect();

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_organizer')->default(false);
            $table->timestamp('banned_at')->nullable();
            $table->string('whatsapp_group')->nullable();
        });

        foreach ($memberships as $membership) {
            DB::table('users')->where('id', $membership->user_id)->update([
                'is_organizer' => (bool) $membership->is_organizer,
                'banned_at' => $membership->banned_at,
            ]);
        }

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

        foreach (['matches', 'guests', 'players', 'ratings', 'player_evaluations'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropConstrainedForeignId('group_id'));
        }

        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');
    }
};
