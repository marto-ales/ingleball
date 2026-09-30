<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupMembershipService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Adds an account to a group without a join code. A person can belong to
 * several groups with one login, so this is the console shortcut for the
 * cases the screen cannot cover.
 */
class AddUserToGroupCommand extends Command
{
    protected $signature = 'groups:add
                            {group : Nombre o código del grupo}
                            {user : Usuario o correo}
                            {--organizer : Dejarlo como organizador de ese grupo}';

    protected $description = 'Suma una cuenta a un grupo (varios grupos, una sola cuenta).';

    public function handle(GroupMembershipService $memberships): int
    {
        $group = $this->findGroup((string) $this->argument('group'));
        $user = $this->findUser((string) $this->argument('user'));

        if ($group === null || $user === null) {
            return self::FAILURE;
        }

        $isOrganizer = (bool) $this->option('organizer');

        if ($user->isMemberOf($group->id)) {
            if ($isOrganizer) {
                $user->memberships()->where('group_id', $group->id)->update(['is_organizer' => true]);
                $this->info($user->name.' ya pertenecía a '.$group->name.'; ahora lo organiza.');
            } else {
                $this->info($user->name.' ya pertenecía a '.$group->name.'.');
            }

            return self::SUCCESS;
        }

        $memberships->join($user, $group, $isOrganizer);

        $this->info($user->name.' ('.$user->username.') ahora pertenece a '.$group->name.'.');
        $this->line('  Cuentas: '.implode(', ', $user->groups()->pluck('groups.name')->all()));

        return self::SUCCESS;
    }

    private function findGroup(string $identifier): ?Group
    {
        $query = Group::query()
            ->where('join_code', $identifier)
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($identifier)]);

        $group = $query->first();

        if ($group === null) {
            $this->error('No existe el grupo "'.$identifier.'".');

            return null;
        }

        return $group;
    }

    private function findUser(string $identifier): ?User
    {
        $user = User::where('username', Str::lower($identifier))
            ->orWhere('email', $identifier)
            ->first();

        if ($user === null) {
            $this->error('No existe ninguna cuenta con usuario o correo "'.$identifier.'".');

            return null;
        }

        return $user;
    }
}
