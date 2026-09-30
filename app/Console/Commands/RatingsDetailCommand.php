<?php

namespace App\Console\Commands;

use App\Models\Rating;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RatingsDetailCommand extends Command
{
    protected $signature = 'ratings:detail
                            {--user= : Filtrar por nombre del jugador calificado}
                            {--match= : Filtrar por id del partido}';

    protected $description = 'Detalle de las calificaciones realizadas por los jugadores, agrupadas por jugador calificado, con puntaje y calificador.';

    public function handle(): int
    {
        $ratings = Rating::with(['rater', 'ratedUser', 'ratedGuest', 'match'])
            ->when($this->option('match'), fn ($query, string $id) => $query->where('match_id', $id))
            ->when($this->option('user'), fn ($query, string $name) => $query->whereHas(
                'ratedUser',
                fn ($user) => $user->where('name', 'like', "%{$name}%"),
            ))
            ->orderBy('created_at')
            ->get();

        if ($ratings->isEmpty()) {
            $this->warn('No hay calificaciones que mostrar.');

            return self::SUCCESS;
        }

        $players = $this->groupByRated($ratings);

        foreach ($players as $player) {
            $this->headline($player);

            foreach ($player['ratings'] as $rating) {
                $this->line(sprintf(
                    '  %-24s %-30s  Puntaje: %d   Calificado por: %s',
                    $rating->created_at->format('d/m/Y'),
                    $rating->match ? $rating->match->played_at->format('d/m/Y').' · '.$rating->match->title : 'sin partido',
                    $rating->overall,
                    $rating->rater?->name ?? '—',
                ));
            }

            $this->newLine();
        }

        $this->info(sprintf(
            'Total: %d calificaciones de %d %s',
            $ratings->count(),
            $players->count(),
            $players->count() === 1 ? 'jugador calificado' : 'jugadores calificados',
        ));

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{name: string, is_guest: bool, count: int, average: string, ratings: Collection<int, Rating>}>
     */
    private function groupByRated(Collection $ratings): Collection
    {
        $grouped = $ratings->groupBy(function (Rating $rating): string {
            if ($rating->rated_user_id !== null) {
                return 'u'.$rating->rated_user_id;
            }

            return $rating->rated_guest_id !== null ? 'g'.$rating->rated_guest_id : 'x';
        });

        return $grouped
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'name' => $first->ratedUser?->name ?? $first->ratedGuest?->name ?? '(sin jugador)',
                    'is_guest' => $first->rated_user_id === null && $first->rated_guest_id !== null,
                    'count' => $group->count(),
                    'average' => number_format((float) $group->avg('overall'), 1),
                    'ratings' => $group->values(),
                ];
            })
            ->sortBy(fn (array $player): string => mb_strtolower($player['name']))
            ->values();
    }

    /**
     * @param  array{name: string, is_guest: bool, count: int, average: string, ratings: Collection<int, Rating>}  $player
     */
    private function headline(array $player): void
    {
        $suffix = $player['is_guest'] ? ' (invitado)' : '';

        $this->info(sprintf(
            '%s: %d calificación%s · promedio %s',
            $player['name'].$suffix,
            $player['count'],
            $player['count'] === 1 ? '' : 'es',
            $player['average'],
        ));
    }
}
