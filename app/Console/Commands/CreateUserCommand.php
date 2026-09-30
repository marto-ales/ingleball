<?php

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesAccounts;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupMembershipService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates an account from the console. The account is not tied to a group by
 * itself: --group joins the account to one (optionally as its organizer), the
 * same way registering or groups:add would. One account can play in several
 * groups, so belonging to others is not a problem here.
 */
class CreateUserCommand extends Command
{
    use ResolvesAccounts;

    protected $signature = 'users:create
                            {name : Nombre del jugador}
                            {--username= : Usuario para iniciar sesión (por defecto, derivado del nombre)}
                            {--email= : Correo de contacto}
                            {--phone= : Teléfono}
                            {--password= : Contraseña (por defecto, una aleatoria)}
                            {--group= : Sumar la cuenta a un grupo (nombre o código)}
                            {--organizer : Dejar la cuenta como organizadora en --group}
                            {--managed : Crearla como jugador gestionado (stub para calificar)}';

    protected $description = 'Da de alta una cuenta de jugador, opcionalmente sumándola a un grupo.';

    public function handle(GroupMembershipService $memberships): int
    {
        $name = trim((string) $this->argument('name'));

        $username = Str::lower(trim((string) ($this->option('username') ?: $this->uniqueUsername($name))));

        $validator = Validator::make([
            'name' => $name,
            'username' => $username,
            'email' => $this->option('email'),
            'phone' => $this->option('phone'),
        ], [
            'name' => ['required', 'string', 'max:80'],
            'username' => ['required', 'string', 'max:30', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:120', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $group = $this->resolveGroupOption();

        if ($this->option('group') && $group === null) {
            return self::FAILURE;
        }

        if ($this->option('organizer') && $group === null) {
            $this->error('--organizer requiere --group.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::random(32));

        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $validator->validated()['email'] ?? null,
            'phone' => $validator->validated()['phone'] ?? null,
            'password' => $password,
            'is_managed' => $this->option('managed'),
        ]);

        $this->info('Cuenta creada: '.$user->name.' ('.$user->username.')'.($user->isManaged() ? ' [gestionada]' : ''));

        if ($user->isManaged()) {
            $this->line('  Gestión: al registrarse, recupera su historial.');
        } else {
            $this->line('  Contraseña: '.$password);
        }

        if ($group instanceof Group) {
            $memberships->join($user, $group, (bool) $this->option('organizer'));

            $this->line('  Grupo: '.$group->name.($this->option('organizer') ? ' (organizador)' : ''));

            $others = $user->groups()->pluck('groups.name')->reject(fn ($name) => $name === $group->name)->all();

            if ($others !== []) {
                $this->line('  Grupos previos: '.implode(', ', $others));
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function resolveGroupOption(): ?Group
    {
        $identifier = trim((string) $this->option('group'));

        if ($identifier === '') {
            return null;
        }

        return $this->findGroup($this, $identifier);
    }
}
