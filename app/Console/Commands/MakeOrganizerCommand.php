<?php

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesAccounts;
use App\Models\GroupMembership;
use Illuminate\Console\Command;

/**
 * The organizer flag lives on the membership: it grants what a person may do
 * here (rate, expel, invite) without changing any other groups they share.
 * Making someone an organizer requires them to already belong to that group.
 */
class MakeOrganizerCommand extends Command
{
    use ResolvesAccounts;

    protected $signature = 'users:organizer
                            {user : Usuario o correo de la cuenta}
                            {group : Nombre o código del grupo}';

    protected $description = 'Deja a un miembro como organizador de un grupo (por pertenencia, sin tocar otros grupos).';

    public function handle(): int
    {
        $user = $this->findUser($this, (string) $this->argument('user'));
        $group = $this->findGroup($this, (string) $this->argument('group'));

        if ($user === null || $group === null) {
            return self::FAILURE;
        }

        $membership = $user->membershipIn($group->id);

        if (! $membership instanceof GroupMembership) {
            $this->error($user->name.' no está en '.$group->name.'. Agregalo primero con groups:add.');

            return self::FAILURE;
        }

        $membership->update(['is_organizer' => true]);

        $this->info($user->name.' es organizador de '.$group->name.'.');

        return self::SUCCESS;
    }
}
