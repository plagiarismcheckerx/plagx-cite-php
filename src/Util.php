<?php

declare(strict_types=1);

namespace Plagx\Cite;

/** @internal Date, text and page helpers shared by the styles. */
final class Util
{
    public const MONTHS = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    public const MONTHS_MLA = [
        'Jan.', 'Feb.', 'Mar.', 'Apr.', 'May', 'June',
        'July', 'Aug.', 'Sept.', 'Oct.', 'Nov.', 'Dec.',
    ];

    /** @return array{year: ?int, month: ?int, day: ?int} */
    public static function getDate(array $ref): array
    {
        $parts = $ref['issued']['date-parts'][0] ?? null;
        if (is_array($parts) && !empty($parts[0])) {
            return [
                'year' => (int) $parts[0],
                'month' => !empty($parts[1]) ? (int) $parts[1] : null,
                'day' => !empty($parts[2]) ? (int) $parts[2] : null,
            ];
        }
        if (!empty($ref['year'])) {
            return ['year' => (int) $ref['year'], 'month' => null, 'day' => null];
        }
        return ['year' => null, 'month' => null, 'day' => null];
    }

    public static function getYear(array $ref): ?string
    {
        $year = self::getDate($ref)['year'];
        return $year ? (string) $year : null;
    }

    /** Lowercase, strip accents and punctuation; used for comparisons and keys. */
    public static function normalizeText(mixed $value): string
    {
        $s = (string) ($value ?? '');
        if (class_exists(\Normalizer::class)) {
            $s = (string) \Normalizer::normalize($s, \Normalizer::FORM_KD);
            $s = (string) preg_replace('/[\x{0300}-\x{036f}]/u', '', $s);
        } else {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($ascii !== false) {
                $s = $ascii;
            }
        }
        $s = mb_strtolower($s, 'UTF-8');
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $s));
    }

    public static function endsWithTerminal(string $value): bool
    {
        return (bool) preg_match('/[.?!]["”’]?$/u', trim($value));
    }

    /** Period to append unless the text already ends with terminal punctuation. */
    public static function dot(string $value): string
    {
        return self::endsWithTerminal($value) ? '' : '.';
    }

    public static function pageRange(mixed $page, string $dash = '–'): string
    {
        return (string) preg_replace('/\s*[-–—]+\s*/u', $dash, trim((string) $page));
    }

    public static function isPageRange(mixed $page): bool
    {
        return (bool) preg_match('/[-–—]/u', (string) $page);
    }

    public static function ordinal(mixed $value): string
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^\s*\d+\s*$/', $value))) {
            return (string) $value;
        }
        $n = (int) $value;
        $mod100 = $n % 100;
        if ($mod100 >= 11 && $mod100 <= 13) {
            return "{$n}th";
        }
        return $n . ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th');
    }

    public static function doiURL(string $doi): string
    {
        return "https://doi.org/{$doi}";
    }

    /** Preferred link for a reference: a valid DOI first, then URL. */
    public static function link(array $ref): string
    {
        $doi = !empty($ref['DOI']) ? Doi::normalize((string) $ref['DOI']) : null;
        if ($doi !== null) {
            return self::doiURL($doi);
        }
        return (string) ($ref['URL'] ?? '');
    }

    /** JavaScript-style truthiness for optional fields. */
    public static function filled(mixed $value): bool
    {
        return $value !== null && $value !== false && $value !== '' && $value !== 0 && $value !== [];
    }
}
