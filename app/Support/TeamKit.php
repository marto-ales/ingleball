<?php

namespace App\Support;

use App\Models\MatchTeam;
use App\Models\Partido;

/**
 * Which shirt each side brings. The draw comes from the match itself, so it
 * changes from match to match but never flips while the same match is read,
 * nor when the lineup is regenerated.
 */
class TeamKit
{
    /** The white and black circles read as light and dark kit. */
    public const LIGHT = '⚪';

    public const DARK = '⚫';

    public const LIGHT_LABEL = 'Camiseta clara';

    public const DARK_LABEL = 'Camiseta oscura';

    /**
     * Icon and wording for each side.
     *
     * @return array{A: array{icon: string, label: string}, B: array{icon: string, label: string}}
     */
    public function shirts(Partido $match): array
    {
        $aIsLight = $this->lightSide($match) === MatchTeam::TEAM_A;

        return [
            MatchTeam::TEAM_A => $aIsLight
                ? ['icon' => self::LIGHT, 'label' => self::LIGHT_LABEL]
                : ['icon' => self::DARK, 'label' => self::DARK_LABEL],
            MatchTeam::TEAM_B => $aIsLight
                ? ['icon' => self::DARK, 'label' => self::DARK_LABEL]
                : ['icon' => self::LIGHT, 'label' => self::LIGHT_LABEL],
        ];
    }

    public function lightSide(Partido $match): string
    {
        return crc32('ingleball-kit-'.$match->id) % 2 === 0
            ? MatchTeam::TEAM_A
            : MatchTeam::TEAM_B;
    }
}
