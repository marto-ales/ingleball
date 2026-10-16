<?php

namespace App\Support;

use App\Models\User;

/**
 * "Novedades del sitio": the welcome message shown on the dashboard while
 * there are changes the account hasn't seen yet. The entries live in
 * config/changelog.php, newest first, and each account remembers the id of the
 * latest one it already acknowledged, so only what is really new shows up.
 */
class Changelog
{
    /**
     * @return array<int, array{id: string, date: string, title: string, changes: array<int, string>}>
     */
    public function all(): array
    {
        return config('changelog', []);
    }

    public function latestId(): ?string
    {
        return $this->all()[0]['id'] ?? null;
    }

    /**
     * Entries newer than the one the person already saw, at most $limit of
     * them. An account that has seen nothing yet gets the newest few instead
     * of the whole history.
     *
     * @return array<int, array{id: string, date: string, title: string, changes: array<int, string>}>
     */
    public function unseen(?User $user, int $limit = 3): array
    {
        // A guest has no account to greet, and a fresh account still gets the
        // newest few entries.
        if ($user === null || $limit < 1) {
            return [];
        }

        $seen = $user?->last_seen_changelog;
        $unseen = [];

        foreach ($this->all() as $entry) {
            if ($seen !== null && ($entry['id'] ?? null) === $seen) {
                break;
            }

            $unseen[] = $entry;

            if (count($unseen) >= $limit) {
                break;
            }
        }

        return $unseen;
    }

    public function hasUnseen(?User $user): bool
    {
        return $this->unseen($user, 1) !== [];
    }

    /**
     * Remember that the person acknowledged everything up to $id (the latest
     * entry by default).
     */
    public function markSeen(User $user, ?string $id = null): void
    {
        $user->update(['last_seen_changelog' => $id ?? $this->latestId()]);
    }
}
