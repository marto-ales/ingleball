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

        if (Auth::user()->isBanned()) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'identity' => __('Tu cuenta fue bloqueada. Contactá a un organizador.'),
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
