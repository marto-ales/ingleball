<?php

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesAccounts;
use App\Models\GroupMembership;
use Illuminate\Console\Command;

/**
 * The organizer flag lives on the membership: removing it here only demotes
 * the person in this group. They stay a member and keep their rights in any
 * other group they run or play in.
 */
class RemoveOrganizerCommand extends Command
{
    use ResolvesAccounts;

    protected $signature = 'users:unorganizer
                            {user : Usuario o correo de la cuenta}
                            {group : Nombre o código del grupo}';

    protected $description = 'Quita el rol de organizador a un miembro en un grupo (sigue perteneciendo al grupo).';

    public function handle(): int
    {
        $user = $this->findUser($this, (string) $this->argument('user'));
        $group = $this->findGroup($this, (string) $this->argument('group'));

        if ($user === null || $group === null) {
            return self::FAILURE;
        }

        $membership = $user->membershipIn($group->id);

        if (! $membership instanceof GroupMembership) {
            $this->error($user->name.' no está en '.$group->name.'.');

            return self::FAILURE;
        }

        $membership->update(['is_organizer' => false]);

        $this->info($user->name.' ya no organiza '.$group->name.', pero sigue como miembro.');

        return self::SUCCESS;
    }
}
