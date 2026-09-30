<?php

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesAccounts;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Accounts are shared across groups: each row is one person with every group
 * they play in and which ones they organize, and --group only filters the
 * list by membership (what organized elsewhere has nothing to do with it).
 * Managed players (stubs that have not registered yet) are flagged so an
 * operator knows they are not real logins waiting for their owner to claim
 * them.
 */
class ListUsersCommand extends Command
{
    use ResolvesAccounts;

    protected $signature = 'users:list
                            {--group= : Filtrar por un grupo (nombre o código)}';

    protected $description = 'Lista las cuentas del sistema con sus grupos y roles.';

    public function handle(): int
    {
        $query = User::query()->with('groups')->orderBy('name');

        $groupFilter = trim((string) $this->option('group'));

        if ($groupFilter !== '') {
            $group = $this->findGroup($this, $groupFilter);

            if ($group === null) {
                return self::FAILURE;
            }

            $query->whereHas('memberships', fn ($query) => $query->where('group_id', $group->id));
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('No hay cuentas todavía. Creá una con users:create.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Usuario', 'Nombre', 'Correo', 'Teléfono', 'Grupos', 'Gestionada'],
            $users->map(fn (User $user) => [
                $user->id,
                $user->username,
                $user->name,
                $user->email ?: '—',
                $user->phone ?: '—',
                $user->groups
                    ->map(fn ($group) => $group->pivot->is_organizer ? $group->name.' (org)' : $group->name)
                    ->join(', '),
                $user->isManaged() ? 'sí' : 'no',
            ])
        );

        $this->line('Total: '.$users->count().' cuentas.');

        return self::SUCCESS;
    }
}
