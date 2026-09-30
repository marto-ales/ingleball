<?php

namespace App\Console\Commands;

use App\Models\Group;
use Illuminate\Console\Command;

/**
 * Every group is its own world: listing them shows the founding data, how many
 * accounts play there and how many run it, so a console operator sees at a
 * glance which worlds exist and which ones are being used.
 */
class ListGroupsCommand extends Command
{
    protected $signature = 'groups:list';

    protected $description = 'Lista los grupos del sistema con su código y su gente.';

    public function handle(): int
    {
        $groups = Group::query()
            ->withCount('memberships')
            ->withCount(['memberships as organizers_count' => fn ($query) => $query->where('is_organizer', true)])
            ->orderBy('name')
            ->get();

        if ($groups->isEmpty()) {
            $this->info('No hay grupos todavía. Creá uno con groups:create.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Grupo', 'Código', 'WhatsApp', 'Miembros', 'Organizadores'],
            $groups->map(fn (Group $group) => [
                $group->id,
                $group->name,
                $group->join_code,
                $group->whatsapp_group ?: '—',
                $group->memberships_count,
                $group->organizers_count,
            ])
        );

        $this->line('Total: '.$groups->count().' grupos · '.$groups->sum('memberships_count').' membresías.');

        return self::SUCCESS;
    }
}
