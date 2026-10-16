<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ChangelogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Three entries, newest first: a, b, c. Tests set them instead of relying
     * on config/changelog.php so releases don't break them.
     */
    private function fakeEntries(): void
    {
        config(['changelog' => [
            ['id' => 'a', 'date' => '2026-10-16', 'title' => 'Cambio reciente', 'changes' => ['Se agregó algo nuevo.']],
            ['id' => 'b', 'date' => '2026-10-01', 'title' => 'Cambio anterior', 'changes' => ['Algo anterior.']],
            ['id' => 'c', 'date' => '2026-09-01', 'title' => 'Cambio viejo', 'changes' => ['Algo viejo.']],
        ]]);
    }

    public function test_signing_in_greets_with_the_latest_changes(): void
    {
        $this->fakeEntries();

        User::factory()->create(['username' => 'pepe', 'password' => Hash::make('secret123')]);

        $this->post('/login', ['identity' => 'pepe', 'password' => 'secret123'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Novedades del sitio')
            ->assertSee('Cambio reciente')
            ->assertSee('Se agregó algo nuevo.');
    }

    public function test_the_welcome_modal_is_not_shown_when_there_is_nothing_new(): void
    {
        $this->fakeEntries();

        User::factory()->create([
            'username' => 'pepe',
            'password' => Hash::make('secret123'),
            'last_seen_changelog' => 'a',
        ]);

        $this->post('/login', ['identity' => 'pepe', 'password' => 'secret123'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Novedades del sitio');
    }

    public function test_only_entries_newer_than_the_last_seen_one_are_announced(): void
    {
        $this->fakeEntries();

        $user = User::factory()->create(['last_seen_changelog' => 'b']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cambio reciente')
            ->assertDontSee('Cambio anterior')
            ->assertDontSee('Cambio viejo');
    }

    public function test_a_new_account_is_shown_at_most_the_latest_few_entries(): void
    {
        config(['changelog' => [
            ['id' => 'a', 'date' => '2026-10-16', 'title' => 'Cambio 1', 'changes' => ['Uno.']],
            ['id' => 'b', 'date' => '2026-10-15', 'title' => 'Cambio 2', 'changes' => ['Dos.']],
            ['id' => 'c', 'date' => '2026-10-14', 'title' => 'Cambio 3', 'changes' => ['Tres.']],
            ['id' => 'd', 'date' => '2026-10-13', 'title' => 'Cambio 4', 'changes' => ['Cuatro.']],
        ]]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cambio 1')
            ->assertSee('Cambio 3')
            ->assertDontSee('Cambio 4');
    }

    public function test_the_modal_only_shows_on_the_dashboard(): void
    {
        $this->fakeEntries();

        $user = User::factory()->create(['last_seen_changelog' => 'b']);

        $this->actingAs($user)
            ->get(route('stats.index'))
            ->assertOk()
            ->assertDontSee('Novedades del sitio');
    }

    public function test_the_modal_is_gone_after_dismissing(): void
    {
        $this->fakeEntries();

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('changelog.dismiss'))->assertRedirect();

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Novedades del sitio');
    }

    public function test_dismissing_remembers_the_latest_entry(): void
    {
        $this->fakeEntries();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('changelog.dismiss'))
            ->assertRedirect();

        $this->assertSame('a', $user->fresh()->last_seen_changelog);
    }

    public function test_see_all_sends_to_the_full_list(): void
    {
        $this->fakeEntries();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('changelog.dismiss'), ['next' => 'all'])
            ->assertRedirect(route('changelog.index'));

        $this->assertSame('a', $user->fresh()->last_seen_changelog);
    }

    public function test_the_changelog_page_lists_every_entry(): void
    {
        $this->fakeEntries();

        $this->actingAs(User::factory()->create())
            ->get(route('changelog.index'))
            ->assertOk()
            ->assertSee('Cambio reciente')
            ->assertSee('Cambio anterior')
            ->assertSee('Cambio viejo');
    }

    public function test_dismissing_requires_login(): void
    {
        $this->fakeEntries();

        $this->post(route('changelog.dismiss'))->assertRedirect();
    }
}
