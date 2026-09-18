<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::withCount('entries')
            ->with('player')
            ->orderByDesc('is_organizer')
            ->orderBy('name')
            ->get();

        return view('users.index', ['users' => $users]);
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
            'goalkeeping' => ['nullable', 'integer', 'between:0,10'],
        ]);

        $username = $this->uniqueUsername($data['name']);

        $user = User::create([
            'name' => $data['name'],
            'username' => $username,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_managed' => true,
            'is_organizer' => false,
            'password' => Str::random(32),
        ]);

        $user->player()->create([
            'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5,
            'goalkeeping' => $data['goalkeeping'] ?? 5,
        ]);

        return redirect()->route('users.manage.index')
            ->with('status', 'Jugador '.$user->name.' creado (usuario '.$username.'). Si se registra con ese nombre, recupera su historial.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 403, 'No podés editar tu propia cuenta desde aquí; usá tu perfil.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'email', 'max:120'],
            'is_organizer' => ['nullable', 'boolean'],
            'goalkeeping' => ['nullable', 'integer', 'between:0,10'],
        ]);

        $user->forceFill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'is_organizer' => $request->boolean('is_organizer'),
        ])->save();

        if (! empty($data['email'])) {
            $owner = User::where('email', $data['email'])->where('id', '!=', $user->id)->first();
            if ($owner) {
                throw ValidationException::withMessages(['email' => 'Ese correo ya le pertenece a otro jugador.']);
            }

            $user->forceFill(['email' => $data['email']])->save();
        }

        if ($user->player()->exists() && ! empty($data['goalkeeping'])) {
            $user->player()->update(['goalkeeping' => $data['goalkeeping']]);
        }

        return back()->with('status', 'Datos de '.$user->name.' actualizados.');
    }

    public function block(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 403, 'No podés bloquearte a vos mismo.');

        $user->forceFill(['banned_at' => now()])->save();

        return back()->with('status', $user->name.' bloqueado (no puede iniciar sesión).');
    }

    public function unblock(User $user): RedirectResponse
    {
        $user->forceFill(['banned_at' => null])->save();

        return back()->with('status', $user->name.' desbloqueado.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 403, 'No podés eliminar tu propia cuenta.');

        $name = $user->name;
        $user->delete();

        return back()->with('status', 'Cuenta de '.$name.' eliminada.');
    }

    private function uniqueUsername(string $name): string
    {
        $base = Str::lower(Str::slug($name, '_'));

        if ($base === '') {
            $base = 'jugador';
        }

        $candidate = $base;
        $suffix = 1;

        while (User::where('username', $candidate)->exists()) {
            $candidate = $base.'_'.$suffix++;
        }

        return $candidate;
    }
}
