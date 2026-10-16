<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Changelog;
use Tests\TestCase;

final class ChangelogTest extends TestCase
{
    private Changelog $changelog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->changelog = new Changelog;

        config(['changelog' => [
            ['id' => 'a', 'date' => '2026-10-16', 'title' => 'A', 'changes' => ['a']],
            ['id' => 'b', 'date' => '2026-10-01', 'title' => 'B', 'changes' => ['b']],
            ['id' => 'c', 'date' => '2026-09-01', 'title' => 'C', 'changes' => ['c']],
        ]]);
    }

    private function userSeen(?string $id): User
    {
        $user = new User;
        $user->last_seen_changelog = $id;

        return $user;
    }

    public function test_latest_id_is_the_first_entry(): void
    {
        $this->assertSame('a', $this->changelog->latestId());
    }

    public function test_unseen_returns_everything_above_the_last_seen_one(): void
    {
        $unseen = $this->changelog->unseen($this->userSeen('b'));

        $this->assertSame(['a'], array_column($unseen, 'id'));
    }

    public function test_unseen_is_empty_when_the_latest_one_was_seen(): void
    {
        $this->assertSame([], $this->changelog->unseen($this->userSeen('a')));
        $this->assertFalse($this->changelog->hasUnseen($this->userSeen('a')));
    }

    public function test_a_brand_new_account_gets_the_newest_few_only(): void
    {
        $unseen = $this->changelog->unseen($this->userSeen(null), 2);

        $this->assertSame(['a', 'b'], array_column($unseen, 'id'));
    }

    public function test_a_guest_sees_nothing_to_announce(): void
    {
        $this->assertSame([], $this->changelog->unseen(null));
    }
}
