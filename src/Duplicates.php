<?php

declare(strict_types=1);

namespace Plagx\Cite;

/** Duplicate reference detection. */
final class Duplicates
{
    private static function keysFor(array $ref): array
    {
        $keys = [];
        $doi = !empty($ref['DOI']) ? Doi::normalize((string) $ref['DOI']) : null;
        if ($doi !== null) {
            $keys['doi'] = strtolower($doi);
        }
        $title = Util::normalizeText($ref['title'] ?? '');
        if ($title !== '') {
            $lead = !empty($ref['author']) ? Util::normalizeText(Names::familyName(array_values($ref['author'])[0])) : '';
            $keys['title'] = "{$title}|{$lead}";
        }
        return $keys;
    }

    /**
     * Finds references that appear more than once, matched by DOI or by
     * normalized title + first author.
     *
     * @return list<array{indexes: list<int>, reasons: list<string>}>
     */
    public static function find(array $refs): array
    {
        $refs = array_values($refs);
        $keys = array_map([self::class, 'keysFor'], $refs);
        $parent = array_keys($refs);
        $find = static function (int $i) use (&$parent, &$find): int {
            return $parent[$i] === $i ? $i : ($parent[$i] = $find($parent[$i]));
        };
        $firstSeen = [];

        foreach ($keys as $i => $k) {
            foreach ($k as $reason => $value) {
                $id = "{$reason}:{$value}";
                if (isset($firstSeen[$id])) {
                    $parent[$find($i)] = $find($firstSeen[$id]);
                } else {
                    $firstSeen[$id] = $i;
                }
            }
        }

        $groups = [];
        foreach (array_keys($refs) as $i) {
            $groups[$find($i)][] = $i;
        }

        $out = [];
        foreach ($groups as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            $reasons = [];
            foreach (['doi', 'title'] as $reason) {
                $values = array_values(array_filter(array_map(static fn ($i) => $keys[$i][$reason] ?? null, $indexes)));
                if (count($values) > count(array_unique($values))) {
                    $reasons[] = $reason;
                }
            }
            $out[] = ['indexes' => $indexes, 'reasons' => $reasons];
        }
        return $out;
    }
}
