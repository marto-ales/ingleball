<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_form_renders(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Restablecer contraseña');
    }

    public function test_forgot_password_sends_reset_link_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'marta@example.com']);

        $this->post(route('password.email'), ['email' => 'marta@example.com'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_does_not_reveal_whether_email_exists(): void
    {
        Mail::fake();

        $this->post(route('password.email'), ['email' => 'nadie@example.com'])
            ->assertSessionHas('status');

        Mail::assertNothingSent();
    }

    public function test_reset_form_renders(): void
    {
        $this->get(route('password.reset', ['token' => 'abc123']))
            ->assertOk()
            ->assertSee('Nueva contraseña');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'marta@example.com', 'password' => Hash::make('secret123')]);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'marta@example.com',
            'password' => 'nueva123',
            'password_confirmation' => 'nueva123',
        ])->assertRedirect(route('login.show'));

        $this->assertGuest();
        $this->assertTrue(Hash::check('nueva123', $user->fresh()->password));
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'marta@example.com', 'password' => Hash::make('secret123')]);

        $this->from(route('password.reset', ['token' => 'bogus']))
            ->post(route('password.update'), [
                'token' => 'bogus',
                'email' => 'marta@example.com',
                'password' => 'nueva123',
                'password_confirmation' => 'nueva123',
            ])
            ->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('nueva123', $user->fresh()->password));
    }

    public function test_user_can_login_after_reset(): void
    {
        $user = User::factory()->create(['email' => 'marta@example.com', 'password' => Hash::make('secret123')]);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'marta@example.com',
            'password' => 'nueva123',
            'password_confirmation' => 'nueva123',
        ]);

        $this->post('/login', ['identity' => 'marta@example.com', 'password' => 'nueva123']);

        $this->assertAuthenticated();
    }
}
