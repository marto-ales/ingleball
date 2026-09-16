<?php

namespace App\Services;

use App\Models\MatchEntry;
use App\Models\MatchTeam;
use App\Models\Partido;
use Illuminate\Support\Collection;

class MessagingService
{
    /**
     * Builds a pre-written message for a match: header data plus the updated
     * player list, grouped by generated teams when available.
     *
     * @param  \Illuminate\Support\Collection<int, MatchTeam>  $teamA
     * @param  \Illuminate\Support\Collection<int, MatchTeam>  $teamB
     * @param  \Illuminate\Support\Collection<int, MatchEntry>  $entriesGoing
     * @param  \Illuminate\Support\Collection<int, MatchEntry>  $entriesSubstitute
     */
    public function message(Partido $match, Collection $teamA, Collection $teamB, Collection $entriesGoing, Collection $entriesSubstitute): string
    {
        $lines = [
            '⚽ *Ingleball — ' . $match->title . '*',
            '🗓️ ' . $match->played_at->format('d/m/Y') . ' a las ' . $match->played_at->format('H:i'),
        ];

        if ($match->venue !== null && $match->venue !== '') {
            $lines[] = '📍 ' . $match->venue;
        }

        $lines[] = '';

        if ($teamA->isNotEmpty()) {
            $lines[] = '*Equipo A* (' . $teamA->count() . '):';

            foreach ($teamA as $i => $member) {
                $lines[] = '   ' . ($i + 1) . '. ' . $this->teamName($member);
            }

            $lines[] = '*Equipo B* (' . $teamB->count() . '):';

            foreach ($teamB as $i => $member) {
                $lines[] = '   ' . ($i + 1) . '. ' . $this->teamName($member);
            }
        } else {
            $lines[] = '*Anotados* (' . $entriesGoing->count() . '):';

            foreach ($entriesGoing as $i => $entry) {
                $lines[] = '   ' . ($i + 1) . '. ' . $this->entryName($entry);
            }
        }

        if ($entriesSubstitute->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '*Suplentes* (' . $entriesSubstitute->count() . '):';

            foreach ($entriesSubstitute as $i => $entry) {
                $lines[] = '   ' . ($i + 1) . '. ' . $this->entryName($entry);
            }
        }

        $lines[] = '';
        $lines[] = '¿Te sumás al próximo? Anotate acá: ' . url('/matches/' . $match->id);

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