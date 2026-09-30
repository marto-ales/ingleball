<?php

namespace App\Support;

class Themes
{
    public const DARK = 'dark';

    public const LIGHT = 'light';

    public const ARGENTINA = 'arg';

    public const ARGENTINA_DARK = 'arg-dark';

    public const DEFAULT = self::ARGENTINA_DARK;

    /**
     * @return array<string, string> theme key → label
     */
    public static function all(): array
    {
        return [
            self::DARK => 'Verde oscuro',
            self::LIGHT => 'Verde claro',
            self::ARGENTINA => 'Argentina',
            self::ARGENTINA_DARK => 'Argentina oscuro',
        ];
    }

    public static function isValid(string $theme): bool
    {
        return array_key_exists($theme, self::all());
    }
}