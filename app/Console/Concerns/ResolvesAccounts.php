<?php

namespace App\Console\Concerns;

use App\Models\Group;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Console commands look up a group or an account by the same loose identifiers
 * a person would type: name, code or username, email. Failing to find one is
 * reported on the command's output instead of throwing.
 */
trait ResolvesAccounts
{
    private function findGroup(Command $command, string $identifier): ?Group
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            $command->error('El grupo no puede estar vacío.');

            return null;
        }

        $group = Group::query()
            ->where('join_code', $identifier)
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($identifier)])
            ->first();

        if ($group === null) {
            $command->error('No existe el grupo "'.$identifier.'".');

            return null;
        }

        return $group;
    }

    private function findUser(Command $command, string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            $command->error('El usuario no puede estar vacío.');

            return null;
        }

        $user = User::whereRaw('LOWER(username) = ?', [Str::lower($identifier)])
            ->orWhere('email', $identifier)
            ->first();

        if ($user === null) {
            $command->error('No existe ninguna cuenta con usuario o correo "'.$identifier.'".');

            return null;
        }

        return $user;
    }

    /**
     * A username nobody has taken yet, built from a name and suffixed when the
     * base is already in use.
     */
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
