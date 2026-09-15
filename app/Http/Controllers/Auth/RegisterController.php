<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\ValidCaptcha;
use App\Support\Captcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:80'],
            'username' => ['required', 'string', 'max:30', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:120', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if (Captcha::enabled()) {
            $rules['captcha'] = ['required', 'string', new ValidCaptcha()];
        }

        $data = $request->validate($rules);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'is_organizer' => false,
        ]);

        $user->player()->create([
            'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'overall' => 5,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', '¡Bienvenido a Ingleball, ' . $user->name . '!');
    }
}
