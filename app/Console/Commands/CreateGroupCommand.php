<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupMembershipService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates a group with its invite code. The account named as organizer is
 * added to it; since one account can play in several groups, belonging to
 * others is not a problem here.
 */
class CreateGroupCommand extends Command
{
    protected $signature = 'groups:create
                            {name : Nombre del grupo}
                            {--code= : Código de invitación (por defecto, uno aleatorio de 8 letras)}
                            {--whatsapp= : Enlace del grupo de WhatsApp}
                            {--organizer= : Usuario o correo que organiza el grupo y queda como primer miembro}';

    protected $description = 'Crea un grupo con su código de invitación y suma a su organizador como miembro.';

    public function handle(GroupMembershipService $memberships): int
    {
        $name = trim((string) $this->argument('name'));
        $code = strtoupper(trim((string) ($this->option('code') ?: Group::randomJoinCode())));

        $validator = Validator::make([
            'name' => $name,
            'join_code' => $code,
            'whatsapp_group' => $this->option('whatsapp'),
        ], [
            'name' => ['required', 'string', 'max:80'],
            'join_code' => ['required', 'string', 'min:4', 'max:32', 'unique:groups,join_code'],
            'whatsapp_group' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $organizer = null;

        if ($identifier = trim((string) $this->option('organizer'))) {
            $organizer = $this->findOrganizer($identifier);

            if ($organizer === null) {
                return self::FAILURE;
            }
        }

        $group = Group::create([
            'name' => $name,
            'join_code' => $code,
            'whatsapp_group' => $this->option('whatsapp') ?: null,
        ]);

        if ($organizer instanceof User) {
            $memberships->join($organizer, $group, true);
        }

        $this->info('Grupo creado: '.$group->name.' (id '.$group->id.')');
        $this->line('  Código de invitación: '.$group->join_code);
        $this->line('  Enlace de WhatsApp: '.($group->whatsapp_group ?: '—'));

        if ($organizer instanceof User) {
            $this->line('  Organizador: '.$organizer->name.' ('.$organizer->username.')');

            $others = $organizer->groups()->pluck('groups.name')->reject(fn ($name) => $name === $group->name)->all();

            if ($others !== []) {
                $this->line('  Sigue jugando en: '.implode(', ', $others));
            }
        } else {
            $this->warn('  El grupo quedó sin miembros: usá --organizer para poner a alguien.');
        }

        return self::SUCCESS;
    }

    private function findOrganizer(string $identifier): ?User
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
