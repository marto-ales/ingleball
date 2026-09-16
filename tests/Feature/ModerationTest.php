<?php

namespace Tests\Feature;

use App\Models\Partido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_organizer_is_forbidden_from_user_management(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('users.manage.index'))
            ->assertForbidden();
    }

    public function test_organizer_can_list_users(): void
    {
        $organizer = User::factory()->organizer()->create();
        User::factory()->count(3)->create();

        $this->actingAs($organizer)
            ->get(route('users.manage.index'))
            ->assertOk()
            ->assertSee('Jugadores');
    }

    public function test_organizer_can_create_a_managed_player(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)->post(route('users.manage.store'), [
            'name' => 'Pancho',
            'phone' => '+5491155550088',
        ])->assertRedirect(route('users.manage.index'));

        $this->assertDatabaseHas('users', ['name' => 'Pancho', 'is_managed' => true]);
        $this->assertDatabaseHas('players', ['user_id' => User::where('name', 'Pancho')->first()->id]);
    }

    public function test_managed_player_cannot_login(): void
    {
        $managed = User::factory()->create(['is_managed' => true, 'password' => bcrypt(Str::random(32))]);

        $this->post('/login', ['username' => $managed->username, 'password' => 'nadie_sabe_esto']);

        $this->assertGuest();
    }

    public function test_banned_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'username' => 'marta',
            'password' => Hash::make('secret123'),
            'banned_at' => now(),
        ]);

        $this->from('/login')->post('/login', ['username' => 'marta', 'password' => 'secret123'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_organizer_can_block_unblock_and_delete_users(): void
    {
        $organizer = User::factory()->organizer()->create();
        $target = User::factory()->create();

        $this->actingAs($organizer)
            ->post(route('users.manage.block', $target))
            ->assertRedirect();

        $this->assertNotNull($target->refresh()->banned_at);

        $this->actingAs($organizer)
            ->post(route('users.manage.unblock', $target))
            ->assertRedirect();

        $this->assertNull($target->refresh()->banned_at);

        $this->actingAs($organizer)
            ->delete(route('users.manage.destroy', $target))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_organizer_cannot_delete_themselves(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)
            ->delete(route('users.manage.destroy', $organizer))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $organizer->id]);
    }

    public function test_registration_adopts_managed_user_and_keeps_player_history(): void
    {
        $managed = User::factory()->create(['name' => 'Pancho', 'username' => 'pancho', 'is_managed' => true]);
        $managed->player()->create([
            'speed' => 6, 'skill' => 6, 'passing' => 6, 'shooting' => 6, 'defense' => 5, 'overall' => 6,
        ]);

        $match = Partido::factory()->createdBy(User::factory()->organizer()->create())->create(['played_at' => now()->addDay()]);
        $match->entries()->create(['user_id' => $managed->id, 'role' => 'going']);

        $this->post('/register', [
            'name' => 'Pancho Real',
            'username' => 'pancho',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('dashboard'));

        $managed->refresh();

        $this->assertAuthenticatedAs($managed);
        $this->assertFalse($managed->is_managed);
        $this->assertSame('Pancho Real', $managed->name);
        $this->assertTrue($managed->player()->exists());
        $this->assertDatabaseHas('match_entries', ['match_id' => $match->id, 'user_id' => $managed->id]);
    }

    public function test_registration_keeps_username_unique_for_real_users(): void
    {
        User::factory()->create(['username' => 'marta']);

        $this->post('/register', [
            'name' => 'Otra',
            'username' => 'marta',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }
}