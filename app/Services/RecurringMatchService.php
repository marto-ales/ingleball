<?php

namespace App\Services;

use App\Models\Partido;

class RecurringMatchService
{
    /**
     * Ensures the upcoming occurrence exists for every recurring template
     * (matches flagged `recurring`). Returns the number of matches created.
     */
    public function ensureUpcoming(): int
    {
        $created = 0;

        foreach (Partido::where('recurring', true)->whereNull('recurring_id')->get() as $template) {
            $lastPlayed = $template->played_at;

            $lastCopy = Partido::where('recurring_id', $template->id)
                ->where('played_at', '<=', now())
                ->orderByDesc('played_at')
                ->value('played_at');

            if ($lastCopy !== null) {
                $lastPlayed = $lastCopy;
            }

            // Current occurrence is still upcoming: nothing to open yet.
            if ($lastPlayed->isFuture()) {
                continue;
            }

            $next = (clone $lastPlayed)->addWeek();
            while ($next->lessThanOrEqualTo(now())) {
                $next = (clone $next)->addWeek();
            }

            $exists = Partido::where('recurring_id', $template->id)
                ->where('played_at', $next)
                ->exists();

            if ($exists) {
                continue;
            }

            Partido::create([
                'title' => $next->format('j').' de '.ucfirst($next->translatedFormat('F')),
                'played_at' => $next,
                'venue' => $template->venue,
                'field_value' => $template->field_value,
                'size' => $template->size,
                'status' => Partido::STATUS_OPEN,
                'created_by' => $template->created_by,
                'recurring' => false,
                'recurring_id' => $template->id,
            ]);

            $created++;
        }

        return $created;
    }
}
