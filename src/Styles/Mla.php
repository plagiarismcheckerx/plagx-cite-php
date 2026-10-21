<?php

declare(strict_types=1);

namespace Plagx\Cite\Styles;

use Plagx\Cite\Formatter;
use Plagx\Cite\Names;
use Plagx\Cite\Util;

/** @internal MLA 9th edition (common reference types). */
final class Mla
{
    private const QUOTED_TYPES = [
        'article-journal', 'article-magazine', 'article-newspaper', 'article',
        'chapter', 'paper-conference', 'webpage', 'entry-encyclopedia', 'entry-dictionary', 'post', 'post-weblog',
    ];

    private static function natural(array $n): string
    {
        return $n['literal'] ?? implode(' ', array_filter([$n['given'], $n['family']], static fn ($s) => $s !== ''));
    }

    private static function authorList(array $list): string
    {
        $names = array_map([Names::class, 'nameParts'], array_values($list));
        $first = $names[0];
        $lead = $first['literal'] ?? $first['family'];
        if (!isset($first['literal']) && $first['given'] !== '') {
            $lead .= ', ' . $first['given'];
        }
        if (!isset($first['literal']) && !empty($first['suffix'])) {
            $lead .= ', ' . $first['suffix'];
        }
        return match (count($names)) {
            1 => $lead,
            2 => $lead . ', and ' . self::natural($names[1]),
            default => $lead . ', et al.',
        };
    }

    private static function editorList(array $list): string
    {
        $names = array_map(static fn ($n) => self::natural(Names::nameParts($n)), array_values($list));
        return match (count($names)) {
            1 => $names[0],
            2 => "{$names[0]} and {$names[1]}",
            default => "{$names[0]} et al.",
        };
    }

    private static function dateText(array $ref): string
    {
        $d = Util::getDate($ref);
        $parts = [$d['day'], $d['month'] ? Util::MONTHS_MLA[$d['month'] - 1] : null, $d['year']];
        return implode(' ', array_filter($parts, static fn ($p) => Util::filled($p)));
    }

    private static function pagesText(mixed $page): string
    {
        return (Util::isPageRange($page) ? 'pp.' : 'p.') . ' ' . Util::pageRange($page, '-');
    }

    public static function reference(array $ref, Formatter $f): string
    {
        $type = (string) ($ref['type'] ?? '');
        $parts = [];

        if (!empty($ref['author'])) {
            $authors = self::authorList($ref['author']);
            $parts[] = $f->t($authors . Util::dot($authors));
        }

        $title = (string) ($ref['title'] ?? '');
        if ($title !== '') {
            $parts[] = in_array($type, self::QUOTED_TYPES, true)
                ? $f->t('“' . $title . Util::dot($title) . '”')
                : $f->i($title) . $f->t(Util::dot($title));
        }

        // Container elements, separated by commas and closed with a period.
        $container = Util::filled($ref['container-title'] ?? null) ? (string) $ref['container-title'] : '';
        $elements = [];
        if ($container !== '') {
            $elements[] = $f->i($container);
        }
        if (!empty($ref['editor'])) {
            $elements[] = $f->t('edited by ' . self::editorList($ref['editor']));
        }
        if (Util::filled($ref['edition'] ?? null)) {
            $elements[] = $f->t(Util::ordinal($ref['edition']) . ' ed.');
        }
        if (Util::filled($ref['volume'] ?? null)) {
            $elements[] = $f->t('vol. ' . $ref['volume']);
        }
        if (Util::filled($ref['issue'] ?? null)) {
            $elements[] = $f->t('no. ' . $ref['issue']);
        }
        // Articles name their journal, not the publisher, unless there is no journal (e.g. arXiv preprints).
        if (Util::filled($ref['publisher'] ?? null) && (!str_starts_with($type, 'article') || $container === '')) {
            $elements[] = $f->t((string) $ref['publisher']);
        }
        $date = self::dateText($ref);
        if ($date !== '') {
            $elements[] = $f->t($date);
        }
        if (Util::filled($ref['page'] ?? null)) {
            $elements[] = $f->t(self::pagesText($ref['page']));
        }
        $url = Util::link($ref);
        if ($url !== '') {
            $elements[] = $f->t($url);
        }

        if ($elements) {
            $parts[] = implode($f->t(', '), $elements) . $f->t('.');
        }
        return implode(' ', $parts);
    }

    public static function inText(array $ref, mixed $page = null, bool $narrative = false): string
    {
        $names = array_map([Names::class, 'familyName'], array_values($ref['author'] ?? []));
        $who = match (count($names)) {
            0 => '“' . (Util::filled($ref['title'] ?? null) ? $ref['title'] : 'Untitled') . '”',
            1 => $names[0],
            2 => "{$names[0]} and {$names[1]}",
            default => "{$names[0]} et al.",
        };
        $pages = Util::filled($page) ? Util::pageRange($page, '-') : '';
        if ($narrative) {
            return $pages !== '' ? "{$who} ({$pages})" : $who;
        }
        return '(' . ($pages !== '' ? "{$who} {$pages}" : $who) . ')';
    }
}
