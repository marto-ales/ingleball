<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\ValidCaptcha;
use App\Support\Captcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $rules = [
            'identity' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];

        if (Captcha::enabled()) {
            $rules['captcha'] = ['required', 'string', new ValidCaptcha];
        }

        $credentials = $request->validate($rules);

        $identity = trim($credentials['identity']);

        $user = str_contains($identity, '@')
            ? User::query()->where('email', $identity)->first()
            : User::query()->whereRaw('LOWER(username) = ?', [Str::lower($identity)])->first();

        if (! $user || ! Auth::attempt(
            ['username' => $user->username, 'password' => $credentials['password']],
            $request->boolean('remember'),
        )) {
            throw ValidationException::withMessages([
                'identity' => __('Credenciales incorrectas.'),
            ]);
        }

        // A block belongs to a membership, not to the account: someone kept
        // out of one group can still sign in to play in the others. Only a
        // person blocked everywhere has nothing left to sign in for.
        $signedIn = Auth::user();
        $signedIn->load('memberships');

        if ($signedIn->memberships->isNotEmpty() && $signedIn->memberships->every(fn ($membership) => $membership->isBanned())) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'identity' => __('Estás bloqueado en todos tus grupos. Contactá a un organizador.'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.show');
    }
}
