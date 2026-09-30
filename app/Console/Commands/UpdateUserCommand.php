<?php

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesAccounts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Updates an account's own data (name, username, email, phone, password).
 * The account is shared across every group, so these apply everywhere;
 * per-group things (organizer, block) live on the membership and are handled
 * by their own commands.
 */
class UpdateUserCommand extends Command
{
    use ResolvesAccounts;

    protected $signature = 'users:update
                            {user : Usuario o correo de la cuenta}
                            {--name= : Nombre del jugador}
                            {--username= : Usuario para iniciar sesión}
                            {--email= : Correo de contacto}
                            {--phone= : Teléfono}
                            {--password= : Nueva contraseña}';

    protected $description = 'Modifica los datos de una cuenta (los de la cuenta, no los del grupo).';

    public function handle(): int
    {
        $user = $this->findUser($this, (string) $this->argument('user'));

        if ($user === null) {
            return self::FAILURE;
        }

        $data = [
            'name' => $this->option('name') ?: $user->name,
            'username' => $this->option('username') !== null ? $this->option('username') : $user->username,
            'email' => $this->option('email') !== null ? $this->option('email') : $user->email,
            'phone' => $this->option('phone') !== null ? $this->option('phone') : $user->phone,
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:80'],
            'username' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user->forceFill([
            'name' => $data['name'],
            'username' => Str::lower($data['username']),
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'],
        ]);

        if ($this->option('password') !== null) {
            $user->password = (string) $this->option('password');
        }

        $user->save();

        $this->info('Cuenta actualizada: '.$user->name.' ('.$user->username.')');
        $this->line('  Correo: '.($user->email ?: '—').'  Teléfono: '.($user->phone ?: '—'));
        $this->line('  Contraseña:'.($this->option('password') !== null ? ' actualizada' : ' sin cambios'));

        return self::SUCCESS;
    }
}
