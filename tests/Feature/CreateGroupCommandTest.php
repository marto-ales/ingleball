<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateGroupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_group_with_a_random_code(): void
    {
        $this->artisan('groups:create', ['name' => 'Barrio Norte'])
            ->expectsOutputToContain('Barrio Norte')
            ->assertSuccessful();

        $group = Group::where('name', 'Barrio Norte')->firstOrFail();

        $this->assertSame(8, strlen($group->join_code));
        $this->assertSame(0, $group->users()->count());
    }

    public function test_it_accepts_a_code_and_a_whatsapp_link(): void
    {
        $this->artisan('groups:create', [
            'name' => 'Barrio Norte',
            '--code' => 'barrio2026',
            '--whatsapp' => 'https://chat.whatsapp.com/ABC',
        ])->assertSuccessful();

        $group = Group::where('name', 'Barrio Norte')->firstOrFail();

        $this->assertSame('BARRIO2026', $group->join_code);
        $this->assertSame('https://chat.whatsapp.com/ABC', $group->whatsapp_group);
    }

    public function test_it_refuses_a_code_already_in_use(): void
    {
        $existing = Group::firstOrFail();

        $this->artisan('groups:create', [
            'name' => 'Repetido',
            '--code' => $existing->join_code,
        ])->assertFailed();

        $this->assertDatabaseMissing('groups', ['name' => 'Repetido']);
    }

    public function test_it_refuses_an_organizer_that_does_not_exist(): void
    {
        $this->artisan('groups:create', [
            'name' => 'Barrio Norte',
            '--organizer' => 'nadie',
        ])->assertFailed();

        $this->assertDatabaseMissing('groups', ['name' => 'Barrio Norte']);
    }

    public function test_it_makes_the_organizer_a_member_of_the_new_group(): void
    {
        $user = User::factory()->create(['username' => 'marta']);

        $this->artisan('groups:create', [
            'name' => 'Barrio Norte',
            '--organizer' => 'marta',
        ])->assertSuccessful();

        $group = Group::where('name', 'Barrio Norte')->firstOrFail();

        $this->assertTrue($user->refresh()->isOrganizerIn($group->id));
        $this->assertDatabaseHas('group_user', [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'is_organizer' => 1,
        ]);
        $this->assertDatabaseHas('players', ['user_id' => $user->id, 'group_id' => $group->id]);
    }

    public function test_an_organizer_can_already_play_in_another_group(): void
    {
        $user = User::factory()->create(['username' => 'marta']);
        $before = $user->groups()->pluck('groups.id')->all();

        $this->artisan('groups:create', [
            'name' => 'Barrio Norte',
            '--organizer' => 'marta',
        ])->expectsOutputToContain('Sigue jugando en')
            ->assertSuccessful();

        $group = Group::where('name', 'Barrio Norte')->firstOrFail();
        $user->refresh();

        // One account, two memberships: it organizes the new group and keeps
        // playing in the one it already belonged to.
        $this->assertCount(2, $user->memberships);
        $this->assertTrue($user->isOrganizerIn($group->id));
        $this->assertNotNull($user->playerFor($group->id));
        $this->assertEqualsCanonicalizing($before, $user->groups()->where('groups.id', '!=', $group->id)->pluck('groups.id')->all());
    }
}
