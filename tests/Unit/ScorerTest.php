<?php

namespace Tests\Unit;

use App\Models\Guest;
use App\Services\Scorer;
use Tests\TestCase;

final class ScorerTest extends TestCase
{
    private Scorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scorer = new Scorer;
    }

    public function test_composite_returns_self_when_no_ratings(): void
    {
        $this->assertSame(7.0, $this->scorer->composite(7.0, null, 0));
    }

    public function test_composite_blends_self_and_others(): void
    {
        // 0.4 * 10 + 0.6 * 5 = 4 + 3 = 7
        $this->assertEqualsWithDelta(7.0, $this->scorer->composite(10.0, 5.0, 3), 0.0001);
    }

    public function test_for_guest_uses_overall(): void
    {
        $this->assertSame(8.0, $this->scorer->forGuest(new Guest(['overall' => 8])));
    }

    public function test_for_guest_defaults_to_five(): void
    {
        $this->assertSame(5.0, $this->scorer->forGuest(new Guest));
    }

    public function test_power_weights_speed_above_skill(): void
    {
        $fast = ['speed' => 10, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5];
        $skilled = ['speed' => 5, 'skill' => 10, 'passing' => 5, 'shooting' => 5, 'defense' => 5];

        $this->assertGreaterThan($this->scorer->power($skilled), $this->scorer->power($fast));
    }

    public function test_attributes_for_guest_reads_every_characteristic(): void
    {
        $attributes = $this->scorer->attributesForGuest(new Guest(['speed' => 9, 'skill' => 3]));

        $this->assertSame(9.0, $attributes['speed']);
        $this->assertSame(3.0, $attributes['skill']);
        $this->assertSame(5.0, $attributes['passing']);
        $this->assertSame(5.0, $attributes['defense']);
    }
}
