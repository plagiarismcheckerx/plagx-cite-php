<?php

declare(strict_types=1);

namespace Plagx\Cite;

/** @internal Name parsing and initials. */
final class Names
{
    /**
     * Accepts a CSL name (['family' => .., 'given' => .., 'suffix' => ..] or ['literal' => ..])
     * or a string ("Smith, John" / "John Smith") and returns a CSL name array.
     */
    public static function nameParts(mixed $name): array
    {
        if (is_string($name)) {
            return self::parseNameString($name);
        }
        if (!is_array($name)) {
            return ['family' => '', 'given' => ''];
        }
        if (!empty($name['literal'])) {
            return ['literal' => (string) $name['literal']];
        }
        return [
            'family' => (string) ($name['family'] ?? ''),
            'given' => (string) ($name['given'] ?? ''),
            'suffix' => isset($name['suffix']) ? (string) $name['suffix'] : null,
        ];
    }

    private static function parseNameString(string $value): array
    {
        $s = trim($value);
        if (str_contains($s, ',')) {
            $parts = explode(',', $s);
            $family = array_shift($parts);
            return ['family' => trim($family), 'given' => trim(implode(',', $parts))];
        }
        $parts = preg_split('/\s+/u', $s) ?: [$s];
        if (count($parts) === 1) {
            return ['family' => $s, 'given' => ''];
        }
        $family = array_pop($parts);
        return ['family' => $family, 'given' => implode(' ', $parts)];
    }

    /** "John Ronald" -> "J. R.", "J.R.R." -> "J. R. R.", "Jean-Paul" -> "J.-P." */
    public static function initials(string $given): string
    {
        $out = [];
        foreach (preg_split('/\s+/u', $given, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (preg_match_all('/\p{L}\./u', $part, $m) && implode('', $m[0]) === $part) {
                $out[] = implode(' ', array_map(static fn ($d) => mb_strtoupper($d, 'UTF-8'), $m[0]));
                continue;
            }
            $segments = array_filter(explode('-', $part), static fn ($s) => $s !== '');
            $out[] = implode('-', array_map(
                static fn ($seg) => mb_strtoupper(mb_substr($seg, 0, 1, 'UTF-8'), 'UTF-8') . '.',
                $segments,
            ));
        }
        return implode(' ', $out);
    }

    /** Family name (or literal) used for in-text citations and sorting. */
    public static function familyName(mixed $name): string
    {
        $n = self::nameParts($name);
        return $n['literal'] ?? $n['family'];
    }
}
