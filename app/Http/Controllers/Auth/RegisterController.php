<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\NewRegistration;
use App\Mail\Welcome;
use App\Models\Group;
use App\Models\User;
use App\Rules\ValidCaptcha;
use App\Support\Captcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
            'username' => ['required', 'string', 'max:30', 'alpha_dash'],
            'email' => ['required', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'group_code' => ['required', 'string', 'exists:groups,join_code'],
        ];

        if (Captcha::enabled()) {
            $rules['captcha'] = ['required', 'string', new ValidCaptcha];
        }

        $data = $request->validate($rules);

        $data['username'] = Str::lower($data['username']);

        $group = Group::where('join_code', $data['group_code'])->firstOrFail();

        $usernameOwner = User::whereRaw('LOWER(username) = ?', [$data['username']])->first();
        if ($usernameOwner && ! $usernameOwner->is_managed) {
            throw ValidationException::withMessages([
                'username' => __('El nombre de usuario ya está en uso.'),
            ]);
        }

        $emailOwner = User::where('email', $data['email'])->first();
        if ($emailOwner && ! $emailOwner->is_managed) {
            throw ValidationException::withMessages([
                'email' => __('El correo ya está en uso.'),
            ]);
        }

        $managed = $usernameOwner?->is_managed ? $usernameOwner : ($emailOwner?->is_managed ? $emailOwner : null);

        if ($managed) {
            $user = $managed;
            $user->forceFill([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? $user->phone,
                'email' => $data['email'],
                'password' => $data['password'],
                'is_managed' => false,
            ])->save();

            $user->moveToGroup($group);
        } else {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'group_id' => $group->id,
                'is_organizer' => false,
            ]);
        }

        if (! $user->player()->exists()) {
            $user->player()->create([
                'group_id' => $group->id,
                'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5,
            ]);
        }

        if ($user->email) {
            Mail::to($user)->send(new Welcome($user));
        }

        $organizers = User::where('group_id', $group->id)
            ->where('is_organizer', true)
            ->whereNotNull('email')
            ->get();
        if ($organizers->isNotEmpty()) {
            Mail::to($organizers)->send(new NewRegistration($user));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with(
            'status',
            $managed
                ? '¡Bienvenido a '.$group->name.'! Recuperaste tu historial como '.$user->name.'.'
                : '¡Bienvenido a '.$group->name.', '.$user->name.'!'
        );
    }
}
