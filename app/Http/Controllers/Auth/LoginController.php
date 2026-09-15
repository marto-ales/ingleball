<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\ValidCaptcha;
use App\Support\Captcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];

        if (Captcha::enabled()) {
            $rules['captcha'] = ['required', 'string', new ValidCaptcha()];
        }

        $credentials = $request->validate($rules);

        if (! Auth::attempt(
            ['username' => $credentials['username'], 'password' => $credentials['password']],
            $request->boolean('remember'),
        )) {
            throw ValidationException::withMessages([
                'username' => __('Credenciales incorrectas.'),
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
