<?php

declare(strict_types=1);

namespace Plagx\Cite;

/** Checks a reference list for problems. */
final class Checker
{
    private static function label(array $ref, int $i): string
    {
        return isset($ref['id']) && $ref['id'] !== '' ? (string) $ref['id'] : '#' . ($i + 1);
    }

    /**
     * Errors: duplicate references, invalid DOIs, DOIs not found (with checkDOIs).
     * Warnings: missing title, author or year.
     *
     * Options: checkDOIs (bool, looks up every DOI online), mailto, http (see Doi::lookup()).
     *
     * @return array{references: int, errors: int, warnings: int, issues: list<array{level: string, code: string, index: int, ref: string, message: string}>}
     */
    public static function check(array $refs, array $options = []): array
    {
        $refs = array_values($refs);
        $issues = [];
        $add = static function (string $level, string $code, int $index, string $message) use (&$issues, $refs) {
            $issues[] = ['level' => $level, 'code' => $code, 'index' => $index, 'ref' => self::label($refs[$index], $index), 'message' => $message];
        };

        foreach (Duplicates::find($refs) as $group) {
            $names = array_map(static fn ($i) => self::label($refs[$i], $i), $group['indexes']);
            foreach (array_slice($group['indexes'], 1) as $i) {
                $add('error', 'duplicate', $i, 'Possible duplicate (' . implode(' and ', $group['reasons']) . ' match) of ' . $names[0] . ': ' . implode(', ', $names));
            }
        }

        foreach ($refs as $i => $ref) {
            if (empty($ref['title'])) {
                $add('warning', 'missing-title', $i, 'No title');
            }
            if (empty($ref['author']) && empty($ref['editor'])) {
                $add('warning', 'missing-author', $i, 'No author or editor');
            }
            if (Util::getYear($ref) === null) {
                $add('warning', 'missing-year', $i, 'No year (will show as n.d.)');
            }
            if (!empty($ref['DOI']) && Doi::normalize((string) $ref['DOI']) === null) {
                $add('error', 'invalid-doi', $i, 'Invalid DOI "' . $ref['DOI'] . '"');
            }
        }

        if (!empty($options['checkDOIs'])) {
            foreach ($refs as $i => $ref) {
                $doi = !empty($ref['DOI']) ? Doi::normalize((string) $ref['DOI']) : null;
                if ($doi === null) {
                    continue;
                }
                try {
                    Doi::lookup($doi, $options);
                } catch (\Throwable $e) {
                    $add('error', 'doi-not-found', $i, $e->getMessage());
                }
            }
        }

        // Stable sort: by reference, errors before warnings.
        $order = array_keys($issues);
        usort($order, static function ($a, $b) use ($issues) {
            $x = $issues[$a];
            $y = $issues[$b];
            if ($x['index'] !== $y['index']) {
                return $x['index'] <=> $y['index'];
            }
            if ($x['level'] !== $y['level']) {
                return $x['level'] === 'error' ? -1 : 1;
            }
            return $a <=> $b;
        });
        $issues = array_map(static fn ($k) => $issues[$k], $order);

        $errors = count(array_filter($issues, static fn ($x) => $x['level'] === 'error'));
        return ['references' => count($refs), 'errors' => $errors, 'warnings' => count($issues) - $errors, 'issues' => $issues];
    }
}
