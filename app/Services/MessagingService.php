<?php

namespace App\Services;

use App\Models\MatchEntry;
use App\Models\MatchTeam;
use App\Models\Partido;
use App\Support\TeamKit;
use Illuminate\Support\Collection;

class MessagingService
{
    public function __construct(private TeamKit $kit) {}

    /**
     * Builds a pre-written message for a match: header data plus the updated
     * player list, grouped by generated teams when available.
     *
     * @param  Collection<int, MatchTeam>  $teamA
     * @param  Collection<int, MatchTeam>  $teamB
     * @param  Collection<int, MatchEntry>  $entriesGoing
     * @param  Collection<int, MatchEntry>  $entriesSubstitute
     */
    public function message(Partido $match, Collection $teamA, Collection $teamB, Collection $entriesGoing, Collection $entriesSubstitute): string
    {
        $lines = [
            '⚽ *'.($match->group?->name ?? 'Ingleball').' — '.$match->title.'*',
            '🗓️ '.$match->played_at->format('d/m/Y').' a las '.$match->played_at->format('H:i'),
        ];

        if ($match->venue !== null && $match->venue !== '') {
            $lines[] = '📍 '.$match->venue;
        }

        if ($match->field_value !== null) {
            $cost = $match->costPerPerson();

            $lines[] = $cost !== null
                ? '💰 Valor: $'.number_format($cost, 0, ',', '.').' por persona'
                : '💰 Valor de la cancha: $'.number_format($match->field_value, 0, ',', '.');
        }

        $lines[] = '';

        if ($teamA->isNotEmpty()) {
            $kit = $this->kit->shirts($match);

            $lines[] = '*Equipo A* ('.$teamA->count().'): '.$kit[MatchTeam::TEAM_A]['icon'];

            foreach ($teamA as $i => $member) {
                $lines[] = '   '.($i + 1).'. '.$this->teamName($member);
            }

            $lines[] = '*Equipo B* ('.$teamB->count().'): '.$kit[MatchTeam::TEAM_B]['icon'];

            foreach ($teamB as $i => $member) {
                $lines[] = '   '.($i + 1).'. '.$this->teamName($member);
            }
        } else {
            // A numbered list of every going slot the match format allows
            // (5v5 → 10, 6v6 → 12), with the names that are already signed up
            // and the rest left empty.
            $capacity = $match->capacity();
            $lines[] = '*Anotados* ('.$entriesGoing->count().'/'.$capacity.'):';

            $slots = max($capacity, $entriesGoing->count());

            for ($slot = 1; $slot <= $slots; $slot++) {
                $number = str_pad((string) $slot, 2, '0', STR_PAD_LEFT);
                $entry = $entriesGoing->get($slot - 1);

                $lines[] = $entry !== null
                    ? '   '.$number.'. '.$this->entryName($entry)
                    : '   '.$number.'.';
            }
        }

        if ($entriesSubstitute->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '*Suplentes* ('.$entriesSubstitute->count().'):';

            foreach ($entriesSubstitute as $i => $entry) {
                $lines[] = '   '.($i + 1).'. '.$this->entryName($entry);
            }
        }

        $lines[] = '';

        if ($teamA->isEmpty() && $match->isOpen()) {
            $lines[] = '¿Te sumás al próximo? Anotate acá: '.url('/matches/'.$match->id);
        } else {
            // Once the list is closed there is nothing left to join, but the
            // link to the match itself still goes out.
            $lines[] = '🔗 '.url('/matches/'.$match->id);
        }

        return implode("\n", $lines);
    }

    private function teamName(MatchTeam $member): string
    {
        return $member->user?->name ?? $member->guest?->name ?? '?';
    }

    private function entryName(MatchEntry $entry): string
    {
        return $entry->user?->name ?? $entry->guest?->name ?? '?';
    }
}
