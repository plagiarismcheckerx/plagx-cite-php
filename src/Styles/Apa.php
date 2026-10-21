<?php

declare(strict_types=1);

namespace Plagx\Cite\Styles;

use Plagx\Cite\Formatter;
use Plagx\Cite\Names;
use Plagx\Cite\Util;

/** @internal APA 7th edition (common reference types). */
final class Apa
{
    private const JOURNAL_TYPES = ['article-journal', 'article-magazine', 'article-newspaper', 'article'];
    private const CONTAINER_TYPES = ['chapter', 'paper-conference', 'entry-encyclopedia', 'entry-dictionary'];
    private const DATED_TYPES = ['webpage', 'article-newspaper', 'article-magazine', 'post', 'post-weblog'];

    private static function authorName(mixed $name): string
    {
        $n = Names::nameParts($name);
        if (isset($n['literal'])) {
            return $n['literal'];
        }
        $s = $n['family'];
        if ($n['given'] !== '') {
            $s .= ', ' . Names::initials($n['given']);
        }
        if (!empty($n['suffix'])) {
            $s .= ', ' . $n['suffix'];
        }
        return $s;
    }

    private static function authorList(array $list): string
    {
        $names = array_map([self::class, 'authorName'], array_values($list));
        $count = count($names);
        if ($count === 1) {
            return $names[0];
        }
        if ($count <= 20) {
            return implode(', ', array_slice($names, 0, -1)) . ', & ' . $names[$count - 1];
        }
        return implode(', ', array_slice($names, 0, 19)) . ', . . . ' . $names[$count - 1];
    }

    private static function editorList(array $list): string
    {
        $names = array_map(static function ($name) {
            $n = Names::nameParts($name);
            if (isset($n['literal'])) {
                return $n['literal'];
            }
            return $n['given'] !== '' ? Names::initials($n['given']) . ' ' . $n['family'] : $n['family'];
        }, array_values($list));
        $count = count($names);
        if ($count === 1) {
            $s = $names[0];
        } elseif ($count === 2) {
            $s = implode(' & ', $names);
        } else {
            $s = implode(', ', array_slice($names, 0, -1)) . ', & ' . $names[$count - 1];
        }
        return $s . ' (' . ($count > 1 ? 'Eds.' : 'Ed.') . ')';
    }

    private static function dateText(array $ref): string
    {
        $d = Util::getDate($ref);
        if (!$d['year']) {
            return 'n.d.';
        }
        $s = (string) $d['year'];
        if (in_array($ref['type'] ?? '', self::DATED_TYPES, true) && $d['month']) {
            $s .= ', ' . Util::MONTHS[$d['month'] - 1];
            if ($d['day']) {
                $s .= ' ' . $d['day'];
            }
        }
        return $s;
    }

    private static function titleText(array $ref, Formatter $f): string
    {
        $title = (string) ($ref['title'] ?? '');
        if ($title === '') {
            return '';
        }
        $type = (string) ($ref['type'] ?? '');
        $italic = !in_array($type, self::JOURNAL_TYPES, true) && !in_array($type, self::CONTAINER_TYPES, true);
        $s = $italic ? $f->i($title) : $f->t($title);
        $note = '';
        if (Util::filled($ref['edition'] ?? null)) {
            $note = ' (' . Util::ordinal($ref['edition']) . ' ed.)';
        }
        if ($type === 'thesis') {
            $publisher = Util::filled($ref['publisher'] ?? null) ? ', ' . $ref['publisher'] : '';
            $note = ' [' . (Util::filled($ref['genre'] ?? null) ? $ref['genre'] : 'Thesis') . $publisher . ']';
        }
        if ($note !== '') {
            return $s . $f->t($note . '.');
        }
        return $s . $f->t(Util::dot($title));
    }

    private static function sourceText(array $ref, Formatter $f, string $authors): string
    {
        $type = (string) ($ref['type'] ?? '');
        $container = Util::filled($ref['container-title'] ?? null) ? (string) $ref['container-title'] : '';
        $publisher = Util::filled($ref['publisher'] ?? null) ? (string) $ref['publisher'] : '';

        if (in_array($type, self::JOURNAL_TYPES, true)) {
            // Preprints and similar have no journal; name the repository instead (e.g. arXiv).
            if ($container === '') {
                return $publisher !== '' && $publisher !== $authors ? $f->t($publisher . Util::dot($publisher)) : '';
            }
            $s = $f->i($container);
            if (Util::filled($ref['volume'] ?? null)) {
                $s .= $f->t(', ') . $f->i((string) $ref['volume']);
            }
            if (Util::filled($ref['issue'] ?? null)) {
                $s .= $f->t('(' . $ref['issue'] . ')');
            }
            if (Util::filled($ref['page'] ?? null)) {
                $s .= $f->t(', ' . Util::pageRange($ref['page']));
            }
            return $s . $f->t('.');
        }

        if (in_array($type, self::CONTAINER_TYPES, true)) {
            $s = $f->t('In ');
            if (!empty($ref['editor'])) {
                $s .= $f->t(self::editorList($ref['editor']) . ', ');
            }
            if ($container !== '') {
                $s .= $f->i($container);
            }
            if (Util::filled($ref['page'] ?? null)) {
                $s .= $f->t(' (' . (Util::isPageRange($ref['page']) ? 'pp.' : 'p.') . ' ' . Util::pageRange($ref['page']) . ')');
            }
            $s .= $f->t('.');
            if ($publisher !== '') {
                $s .= $f->t(' ' . $publisher . Util::dot($publisher));
            }
            return $s;
        }

        if ($type === 'thesis') {
            return '';
        }

        // Webpage: site name; book, report and others: publisher. Omitted when it repeats the author.
        $source = $type === 'webpage' ? $container : $publisher;
        if ($source === '' || $source === $authors) {
            return '';
        }
        return $f->t($source . Util::dot($source));
    }

    public static function reference(array $ref, Formatter $f): string
    {
        $authors = !empty($ref['author']) ? self::authorList($ref['author']) : '';
        $date = $f->t('(' . self::dateText($ref) . ').');
        $title = self::titleText($ref, $f);
        $parts = $authors !== '' ? [$f->t($authors . Util::dot($authors)), $date, $title] : [$title, $date];
        $parts[] = self::sourceText($ref, $f, $authors);
        $parts[] = $f->t(Util::link($ref));
        return implode(' ', array_filter($parts, static fn ($p) => $p !== ''));
    }

    public static function inText(array $ref, mixed $page = null, bool $narrative = false): string
    {
        $names = array_map([Names::class, 'familyName'], array_values($ref['author'] ?? []));
        $and = $narrative ? 'and' : '&';

        $detail = Util::getYear($ref) ?? 'n.d.';
        if (Util::filled($page)) {
            $detail .= ', ' . (Util::isPageRange($page) ? 'pp.' : 'p.') . ' ' . Util::pageRange($page);
        }

        // No author: the title stands in, and APA puts the comma inside the quotation marks.
        if (count($names) === 0) {
            $title = Util::filled($ref['title'] ?? null) ? $ref['title'] : 'Untitled';
            return $narrative ? "“{$title}” ({$detail})" : "(“{$title},” {$detail})";
        }

        $who = match (count($names)) {
            1 => $names[0],
            2 => "{$names[0]} {$and} {$names[1]}",
            default => "{$names[0]} et al.",
        };
        return $narrative ? "{$who} ({$detail})" : "({$who}, {$detail})";
    }
}
