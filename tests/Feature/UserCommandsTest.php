<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UserCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_makes_an_account_with_a_derived_username(): void
    {
        $this->artisan('users:create', ['name' => 'Tini Blas'])
            ->expectsOutputToContain('tini_blas')
            ->assertSuccessful();

        $user = User::where('username', 'tini_blas')->firstOrFail();

        $this->assertSame('Tini Blas', $user->name);
        $this->assertFalse($user->isManaged());
        $this->assertTrue(Hash::isHashed($user->password));
    }

    public function test_create_turns_a_duplicate_username_into_a_suffix(): void
    {
        User::factory()->create(['username' => 'tini_blas']);

        $this->artisan('users:create', ['name' => 'Tini Blas'])
            ->expectsOutputToContain('tini_blas_1')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['username' => 'tini_blas_1']);
    }

    public function test_create_with_a_taken_username_fails(): void
    {
        User::factory()->create(['username' => 'pepa']);

        $this->artisan('users:create', ['name' => 'Pepa', '--username' => 'pepa'])
            ->assertFailed();
    }

    public function test_create_joins_the_group_and_can_organize_it(): void
    {
        $this->artisan('users:create', [
            'name' => 'Pepa',
            '--group' => 'Ingleball',
            '--organizer' => true,
        ])->assertSuccessful();

        $user = User::where('username', 'pepa')->firstOrFail();
        $group = Group::where('name', 'Ingleball')->firstOrFail();

        $this->assertTrue($user->isOrganizerIn($group->id));
        $this->assertNotNull($user->playerFor($group->id));
    }

    public function test_create_refuses_an_unknown_group(): void
    {
        $this->artisan('users:create', ['name' => 'Pepa', '--group' => 'Nadie'])
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['name' => 'Pepa']);
    }

    public function test_create_managed_account_gets_no_login_mail_destinations(): void
    {
        $this->artisan('users:create', ['name' => 'Pancho', '--managed' => true])
            ->expectsOutputToContain('[gestionada]')
            ->assertSuccessful();

        $user = User::where('username', 'pancho')->firstOrFail();

        $this->assertTrue($user->isManaged());
    }

    public function test_update_changes_name_username_and_email(): void
    {
        $user = User::factory()->create(['name' => 'Pepa', 'username' => 'pepa', 'email' => 'pepa@a.com']);

        $this->artisan('users:update', [
            'user' => 'pepa',
            '--name' => 'Pepa Dos',
            '--username' => 'pepa2',
            '--email' => 'pepa@b.com',
        ])->assertSuccessful();

        $user->refresh();

        $this->assertSame('Pepa Dos', $user->name);
        $this->assertSame('pepa2', $user->username);
        $this->assertSame('pepa@b.com', $user->email);
    }

    public function test_update_resets_the_password(): void
    {
        $user = User::factory()->create(['username' => 'pepa']);

        $this->artisan('users:update', ['user' => 'pepa', '--password' => 'nueva-clave'])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('nueva-clave', $user->refresh()->password));
    }

    public function test_update_refuses_a_username_another_account_owns(): void
    {
        User::factory()->create(['username' => 'otro']);
        $user = User::factory()->create(['username' => 'pepa']);

        $this->artisan('users:update', ['user' => 'pepa', '--username' => 'otro'])
            ->assertFailed();

        $this->assertSame('pepa', $user->refresh()->username);
    }

    public function test_update_unknown_user_fails(): void
    {
        $this->artisan('users:update', ['user' => 'nadie', '--name' => 'X'])
            ->assertFailed();
    }

    public function test_delete_removes_the_account_and_its_group_trace(): void
    {
        $user = User::factory()->create(['username' => 'pepa']);

        $this->artisan('users:delete', ['user' => 'pepa', '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['username' => 'pepa']);
        $this->assertDatabaseMissing('group_user', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('players', ['user_id' => $user->id]);
    }

    public function test_delete_cancelled_without_confirmation(): void
    {
        $user = User::factory()->create(['username' => 'pepa']);

        $this->artisan('users:delete', ['user' => 'pepa'])
            ->expectsConfirmation('¿Confirmás el borrado definitivo de '.$user->name.'? No hay vuelta atrás.', false)
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['username' => 'pepa']);
    }

    public function test_delete_unknown_user_fails(): void
    {
        $this->artisan('users:delete', ['user' => 'nadie', '--force' => true])
            ->assertFailed();
    }

    public function test_organizer_and_unorganizer_toggle_the_membership_flag(): void
    {
        $group = Group::where('name', 'Ingleball')->firstOrFail();
        $user = User::factory()->create(['username' => 'pepa']);

        $this->artisan('users:organizer', ['user' => 'pepa', 'group' => 'Ingleball'])
            ->assertSuccessful();

        $this->assertTrue($user->refresh()->isOrganizerIn($group->id));

        $this->artisan('users:unorganizer', ['user' => 'pepa', 'group' => 'Ingleball'])
            ->assertSuccessful();

        $this->assertFalse($user->refresh()->isOrganizerIn($group->id));
        $this->assertTrue($user->isMemberOf($group->id));
    }

    public function test_making_an_unrelated_user_organizer_fails(): void
    {
        $other = Group::factory()->create();
        $user = User::factory()->create(['username' => 'pepa']);
        $user->groups()->detach();

        $this->artisan('users:organizer', ['user' => 'pepa', 'group' => $other->name])
            ->assertFailed();

        $this->assertFalse($user->refresh()->isOrganizerIn($other->id));
    }

    public function test_unorganizer_keeps_the_belongs_to_other_groups_intact(): void
    {
        $group = Group::where('name', 'Ingleball')->firstOrFail();
        $other = Group::factory()->create();
        $user = User::factory()->alsoInGroup($other, isOrganizer: true)->create([
            'username' => 'pepa',
        ]);

        $this->artisan('users:unorganizer', ['user' => 'pepa', 'group' => $other->name])
            ->assertSuccessful();

        $this->assertFalse($user->refresh()->isOrganizerIn($other->id));
        $this->assertTrue($user->isMemberOf($group->id));
    }

    public function test_lookup_works_by_email_too(): void
    {
        $user = User::factory()->create(['username' => 'pepa', 'email' => 'pepa@mail.com']);

        $this->artisan('users:update', ['user' => 'pepa@mail.com', '--name' => 'Renombrada'])
            ->assertSuccessful();

        $this->assertSame('Renombrada', $user->refresh()->name);
    }
}
