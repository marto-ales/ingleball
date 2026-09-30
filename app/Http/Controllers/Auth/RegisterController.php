<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\NewRegistration;
use App\Mail\Welcome;
use App\Models\Group;
use App\Models\User;
use App\Rules\ValidCaptcha;
use App\Services\GroupMembershipService;
use App\Support\ActiveGroup;
use App\Support\Captcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * One account, several groups. Registering creates the account and its first
 * membership; the groups after that are added with their code from "Mi grupo",
 * which is why a username that already has a password points there instead of
 * failing.
 */
class RegisterController extends Controller
{
    public function __construct(
        private GroupMembershipService $memberships,
        private ActiveGroup $activeGroup,
    ) {}

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
                'username' => __('Ese usuario ya existe. Iniciá sesión y sumate al grupo con su código.'),
            ]);
        }

        $emailOwner = User::where('email', $data['email'])->first();
        if ($emailOwner && ! $emailOwner->is_managed) {
            throw ValidationException::withMessages([
                'email' => __('Ese correo ya está en uso.'),
            ]);
        }

        $managed = $usernameOwner?->is_managed ? $usernameOwner : ($emailOwner?->is_managed ? $emailOwner : null);

        // A managed player is a stub the organizer of one group created. It is
        // reclaimed by whoever registers with the code of that very group; a
        // different group's code must not pull the account into it.
        if ($managed && ! $managed->isMemberOf($group->id)) {
            throw ValidationException::withMessages([
                'group_code' => __('Ese jugador gestionado pertenece a otro grupo. Usá el código de su grupo para recuperarlo.'),
            ]);
        }

        if ($managed) {
            $user = $managed;
            $user->forceFill([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? $user->phone,
                'email' => $data['email'],
                'password' => $data['password'],
                'is_managed' => false,
            ])->save();
        } else {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
            ]);
        }

        $this->memberships->join($user, $group);

        if ($user->email) {
            Mail::to($user)->send(new Welcome($user, $group));
        }

        $organizers = Group::membersQuery($group->id)
            ->whereHas('memberships', fn ($query) => $query
                ->where('group_id', $group->id)
                ->where('is_organizer', true))
            ->whereNotNull('email')
            ->get();

        if ($organizers->isNotEmpty()) {
            Mail::to($organizers)->send(new NewRegistration($user, $group));
        }

        Auth::login($user);
        $request->session()->regenerate();

        // Sólo después de iniciar sesión: el grupo activo se resuelve sobre la
        // cuenta autenticada, y recién sabemos cuál es la primera membresía.
        $this->activeGroup->activate($group);

        return redirect()->route('dashboard')->with(
            'status',
            $managed
                ? '¡Bienvenido a '.$group->name.'! Recuperaste tu historial como '.$user->name.'.'
                : '¡Bienvenido a '.$group->name.', '.$user->name.'!'
        );
    }
}
