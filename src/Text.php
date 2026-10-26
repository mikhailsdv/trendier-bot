<?php

declare(strict_types=1);

namespace Trendier;

final class Text
{
    /** Russian plural form: plural(5, 'секунду', 'секунды', 'секунд') → 'секунд' */
    public static function plural(int $n, string $one, string $few, string $many): string
    {
        $n10 = $n % 10;
        $n100 = $n % 100;
        return match (true) {
            $n10 === 1 && $n100 !== 11 => $one,
            $n10 >= 2 && $n10 <= 4 && ($n100 < 12 || $n100 > 14) => $few,
            default => $many,
        };
    }

    /** @param non-empty-list<string> $variants */
    public static function pick(array $variants): string
    {
        return $variants[array_rand($variants)];
    }

    /** Removes the indentation of a multi-line literal. */
    public static function dedent(string $text): string
    {
        return trim(preg_replace('/^[ \t]+/m', '', $text));
    }
}
