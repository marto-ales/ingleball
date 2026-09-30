<?php

namespace Tests\Unit;

use App\Services\AlgorithmSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AlgorithmSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_the_config_order_with_goalies_and_random_ties(): void
    {
        $settings = new AlgorithmSettings;

        $this->assertSame(config('balance.attributes'), $settings->order());
        $this->assertTrue($settings->randomTieBreak());
        $this->assertTrue($settings->spreadGoalies());
        $this->assertSame((float) config('balance.self_weight'), $settings->selfWeight());
        $this->assertTrue($settings->weightByForm());
    }

    public function test_weights_follow_the_configured_order(): void
    {
        $settings = new AlgorithmSettings;
        $settings->update(['order' => ['defense', 'speed', 'skill', 'passing', 'shooting']]);

        $weights = $settings->weights();

        $this->assertSame(0.30, $weights['defense']);
        $this->assertSame(0.25, $weights['speed']);
        $this->assertSame(0.15, $weights['skill']);
        $this->assertSame(0.15, $weights['passing']);
        $this->assertSame(0.15, $weights['shooting']);
    }

    public function test_update_persists_every_option(): void
    {
        $settings = new AlgorithmSettings;
        $settings->update([
            'order' => ['skill', 'speed', 'passing', 'shooting', 'defense'],
            'random_tie_break' => false,
            'spread_goalies' => false,
            'self_weight' => 0.3,
            'weight_by_form' => false,
        ]);

        $fresh = new AlgorithmSettings;

        $this->assertSame('skill', $fresh->order()[0]);
        $this->assertSame('speed', $fresh->order()[1]);
        $this->assertFalse($fresh->randomTieBreak());
        $this->assertFalse($fresh->spreadGoalies());
        $this->assertSame(0.3, $fresh->selfWeight());
        $this->assertFalse($fresh->weightByForm());
    }
}
