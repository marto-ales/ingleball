<?php

namespace Tests\Feature;

use App\Mail\NewRegistration;
use App\Mail\Welcome;
use App\Models\Group;
use App\Models\Partido;
use App\Models\User;
use App\Support\Captcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function defaultGroupCode(): string
    {
        return Group::query()->orderBy('id')->value('join_code');
    }

    public function test_login_screen_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Marta',
            'username' => 'marta',
            'email' => 'marta@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'group_code' => $this->defaultGroupCode(),
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'username' => 'marta',
            'email' => 'marta@example.com',
            'group_id' => Group::query()->orderBy('id')->value('id'),
        ]);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_registration_requires_a_valid_group_code(): void
    {
        $this->post('/register', [
            'name' => 'Marta',
            'username' => 'marta',
            'email' => 'marta@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('group_code');

        $this->post('/register', [
            'name' => 'Marta',
            'username' => 'marta',
            'email' => 'marta@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'group_code' => 'NOCODE123',
        ])->assertSessionHasErrors('group_code');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['username' => 'marta']);
    }

    public function test_registration_with_a_code_joins_that_group(): void
    {
        $group = Group::factory()->withCode('OTHERGRP')->create();

        $this->post('/register', [
            'name' => 'Marta',
            'username' => 'marta',
            'email' => 'marta@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'group_code' => 'OTHERGRP',
        ]);

        $this->assertDatabaseHas('users', ['username' => 'marta', 'group_id' => $group->id]);
        $this->assertDatabaseHas('players', ['group_id' => $group->id]);
    }

    public function test_registration_requires_email(): void
    {
        $response = $this->post('/register', [
            'name' => 'Marta',
            'username' => 'marta',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'group_code' => $this->defaultGroupCode(),
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registration_mails_user_and_notifies_organizers_of_that_group(): void
    {
        Mail::fake();
        User::factory()->organizer()->create(['email' => 'org@example.com', 'username' => 'orga']);

        $other = Group::factory()->withCode('OTHERGRP')->create();
        User::factory()->organizer()->inGroup($other)->create(['email' => 'otro@example.com']);

        $this->post('/register', [
            'name' => 'Marta',
            'username' => 'marta',
            'email' => 'marta@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'group_code' => $this->defaultGroupCode(),
        ]);

        Mail::assertSent(Welcome::class, fn ($mail) => $mail->hasTo('marta@example.com'));
        Mail::assertSent(NewRegistration::class, fn ($mail) => $mail->hasTo('org@example.com'));
        Mail::assertNotSent(NewRegistration::class, fn ($mail) => $mail->hasTo('otro@example.com'));
    }

    public function test_users_can_login_with_username_and_password(): void
    {
        User::factory()->create(['username' => 'marta', 'password' => Hash::make('secret123')]);

        $response = $this->post('/login', ['identity' => 'marta', 'password' => 'secret123']);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_users_can_login_with_email(): void
    {
        User::factory()->create(['username' => 'marta', 'email' => 'marta@example.com', 'password' => Hash::make('secret123')]);

        $this->post('/login', ['identity' => 'marta@example.com', 'password' => 'secret123']);

        $this->assertAuthenticated();
    }

    public function test_username_login_is_case_insensitive(): void
    {
        User::factory()->create(['username' => 'marta', 'password' => Hash::make('secret123')]);

        $this->post('/login', ['identity' => 'MaRtA', 'password' => 'secret123']);

        $this->assertAuthenticated();
    }

    public function test_register_stores_username_in_lowercase(): void
    {
        $this->post('/register', [
            'name' => 'Marta',
            'username' => 'Marta_88',
            'email' => 'marta88@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'group_code' => $this->defaultGroupCode(),
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['username' => 'marta_88']);
    }

    public function test_users_cannot_login_with_wrong_password(): void
    {
        User::factory()->create(['username' => 'marta', 'password' => Hash::make('secret123')]);

        $this->post('/login', ['identity' => 'marta', 'password' => 'wrong']);

        $this->assertGuest();
    }

    public function test_login_requires_captcha_when_enabled(): void
    {
        config(['captcha.enabled' => true]);
        User::factory()->create(['username' => 'marta', 'password' => Hash::make('secret123')]);

        $this->from('/login')->post('/login', [
            'identity' => 'marta',
            'password' => 'secret123',
        ]);

        $this->assertGuest();
        $this->get('/login')->assertOk();
    }

    public function test_login_succeeds_with_correct_captcha(): void
    {
        config(['captcha.enabled' => true]);
        session([Captcha::SESSION_KEY => '14']);
        User::factory()->create(['username' => 'marta', 'password' => Hash::make('secret123')]);

        $response = $this->post('/login', [
            'identity' => 'marta',
            'password' => 'secret123',
            'captcha' => '14',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login.show'));
    }

    public function test_dashboard_renders_for_user_without_entries(): void
    {
        $user = User::factory()->create();
        Partido::factory()->count(3)->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Próximos');
    }
}
