<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function show(): View
    {
        return view('auth.forgot-password');
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink(['email' => $data['email']]);

        // Same message regardless of whether the account exists, to avoid
        // revealing which emails are registered.
        return back()->with('status', 'Si hay una cuenta con ese correo, recibirás un enlace para restablecer la contraseña.');
    }
}
