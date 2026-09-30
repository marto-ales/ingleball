<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;

class Captcha
{
    public const SESSION_KEY = 'captcha_answer';

    private int $a;

    private int $b;

    private string $op;

    public static function enabled(): bool
    {
        return (bool) config('captcha.enabled', true);
    }

    public static function make(): self
    {
        $captcha = new self;

        $captcha->op = ['+', '-', 'x'][random_int(0, 2)];
        $captcha->a = $captcha->op === '-'
            ? random_int(2, 9)
            : random_int(1, 9);
        $captcha->b = $captcha->op === '-'
            ? random_int(1, $captcha->a - 1)
            : random_int(1, 9);

        Session::put(self::SESSION_KEY, (string) $captcha->answer());

        return $captcha;
    }

    public function question(): string
    {
        return "{$this->a} {$this->op} {$this->b}";
    }

    public function answer(): int
    {
        return match ($this->op) {
            '-' => $this->a - $this->b,
            'x' => $this->a * $this->b,
            default => $this->a + $this->b,
        };
    }

    public function imageUri(): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg());
    }

    private function svg(): string
    {
        $backgrounds = ['#eef4ef', '#f3f6f3', '#f6f4ef', '#eef0f4', '#f6f1ef'];
        $ink = ['#0b5c34', '#1c2521', '#2d4a3e', '#7a3b12', '#4a5560', '#14602e'];

        $bg = $backgrounds[array_rand($backgrounds)];
        $parts = ["<rect width=\"220\" height=\"60\" rx=\"12\" fill=\"{$bg}\"/>"];

        for ($i = 0; $i < 5; $i++) {
            $parts[] = sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#c4cfc5" stroke-width="1"/>',
                random_int(0, 220), random_int(0, 60), random_int(0, 220), random_int(0, 60)
            );
        }

        $x = 26;
        $y = 40;

        foreach (str_split($this->question()) as $ch) {
            $rotation = random_int(-16, 16);
            $size = random_int(22, 30);
            $color = $ink[array_rand($ink)];
            $parts[] = '<text x="'.$x.'" y="'.$y.'" font-size="'.$size.'" font-family="monospace" font-weight="bold" fill="'.$color.'" transform="rotate('.$rotation.' '.$x.' '.$y.')">'.htmlspecialchars($ch, ENT_QUOTES).'</text>';
            $x += random_int(20, 27);
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="60" viewBox="0 0 220 60">'.implode('', $parts).'</svg>';
    }
}
