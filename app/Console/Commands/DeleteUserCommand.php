<?php

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesAccounts;
use Illuminate\Console\Command;

/**
 * Deletes an account completely: its memberships and everything each group
 * knew about it (profile, ratings, evaluations, entries it made, matches it
 * created) goes with it, because the account is the account. Use groups:add
 * and users:update to change memberships or data instead; there is no undo.
 */
class DeleteUserCommand extends Command
{
    use ResolvesAccounts;

    protected $signature = 'users:delete
                            {user : Usuario o correo de la cuenta}
                            {--force : Borrar sin pedir confirmación}';

    protected $description = 'Da de baja una cuenta y todo lo que dejó en los grupos.';

    public function handle(): int
    {
        $user = $this->findUser($this, (string) $this->argument('user'));

        if ($user === null) {
            return self::FAILURE;
        }

        $groupNames = $user->groups()->pluck('groups.name')->all();
        $matchesCreated = $user->matchesCreated()->count();

        $this->info('Cuenta: '.$user->name.' ('.$user->username.')');
        $this->line('  Grupos: '.($groupNames !== [] ? implode(', ', $groupNames) : 'ninguno'));
        $this->line('  Partidos creados que se borran: '.$matchesCreated);
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('¿Confirmás el borrado definitivo de '.$user->name.'? No hay vuelta atrás.', false)) {
            $this->info('Borrado cancelado.');

            return self::SUCCESS;
        }

        $user->delete();

        $this->info('Cuenta borrada.');

        return self::SUCCESS;
    }
}
