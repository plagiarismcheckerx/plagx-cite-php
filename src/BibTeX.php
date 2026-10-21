<?php

declare(strict_types=1);

namespace Plagx\Cite;

/** BibTeX export and BibTeX/BibLaTeX import. */
final class BibTeX
{
    private const TO_BIBTEX_TYPE = [
        'article-journal' => 'article',
        'article-magazine' => 'article',
        'article-newspaper' => 'article',
        'article' => 'article',
        'book' => 'book',
        'chapter' => 'incollection',
        'paper-conference' => 'inproceedings',
        'report' => 'techreport',
        'thesis' => 'phdthesis',
    ];

    private const FROM_BIBTEX_TYPE = [
        'article' => 'article-journal',
        'book' => 'book',
        'inbook' => 'chapter',
        'incollection' => 'chapter',
        'inproceedings' => 'paper-conference',
        'conference' => 'paper-conference',
        'techreport' => 'report',
        'report' => 'report',
        'phdthesis' => 'thesis',
        'mastersthesis' => 'thesis',
        'thesis' => 'thesis',
        'online' => 'webpage',
    ];

    private const MONTH_NAMES = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    private const ACCENTS = [
        '"' => "\u{0308}", "'" => "\u{0301}", '`' => "\u{0300}", '^' => "\u{0302}", '~' => "\u{0303}",
        '=' => "\u{0304}", '.' => "\u{0307}", 'c' => "\u{0327}", 'v' => "\u{030C}", 'u' => "\u{0306}", 'H' => "\u{030B}",
    ];

    // ------------------------------------------------------------- export

    private static function escapeValue(string $value): string
    {
        return (string) preg_replace_callback(
            '/[\\\\&%$#_{}]/',
            static fn ($m) => $m[0] === '\\' ? '\\textbackslash{}' : '\\' . $m[0],
            $value,
        );
    }

    private static function bibName(mixed $name): string
    {
        $n = Names::nameParts($name);
        if (isset($n['literal'])) {
            return '{' . $n['literal'] . '}';
        }
        return implode(', ', array_filter([$n['family'], $n['suffix'] ?? null, $n['given']], [Util::class, 'filled']));
    }

    private static function baseKey(array $ref): string
    {
        $lead = !empty($ref['author']) ? Names::familyName(array_values($ref['author'])[0]) : 'anon';
        $word = '';
        foreach (explode(' ', Util::normalizeText($ref['title'] ?? '')) as $w) {
            if (strlen($w) > 3 && !in_array($w, ['with', 'from', 'that', 'this'], true)) {
                $word = $w;
                break;
            }
        }
        $year = Util::getDate($ref)['year'] ?? '';
        $key = str_replace(' ', '', Util::normalizeText($lead)) . $year . $word;
        return $key !== '' ? $key : 'ref';
    }

    private static function entry(array $ref, string $key): string
    {
        $type = self::TO_BIBTEX_TYPE[$ref['type'] ?? ''] ?? 'misc';
        $container = $ref['container-title'] ?? null;
        $d = Util::getDate($ref);
        $names = static fn ($list) => implode(' and ', array_map([self::class, 'bibName'], array_values($list)));

        $fields = [
            ['author', !empty($ref['author']) ? $names($ref['author']) : null],
            ['editor', !empty($ref['editor']) ? $names($ref['editor']) : null],
            ['title', $ref['title'] ?? null],
            [$type === 'article' ? 'journal' : 'booktitle', in_array($type, ['article', 'incollection', 'inproceedings'], true) ? $container : null],
            ['howpublished', $type === 'misc' ? $container : null],
            ['year', $d['year']],
            ['month', $d['month'] ? self::MONTH_NAMES[$d['month'] - 1] : null],
            ['volume', $ref['volume'] ?? null],
            ['number', $ref['issue'] ?? null],
            ['pages', Util::filled($ref['page'] ?? null) ? preg_replace('/\s*[-–—]+\s*/u', '--', (string) $ref['page']) : null],
            ['edition', $ref['edition'] ?? null],
            [$type === 'phdthesis' ? 'school' : ($type === 'techreport' ? 'institution' : 'publisher'), $ref['publisher'] ?? null],
            ['address', $ref['publisher-place'] ?? null],
            ['isbn', $ref['ISBN'] ?? null],
            ['doi', $ref['DOI'] ?? null],
            ['url', $ref['URL'] ?? null],
        ];

        $body = [];
        foreach ($fields as [$name, $value]) {
            if (!Util::filled($value)) {
                continue;
            }
            $raw = in_array($name, ['author', 'editor', 'url', 'doi', 'month'], true);
            $body[] = "  {$name} = {" . ($raw ? (string) $value : self::escapeValue((string) $value)) . '}';
        }
        return "@{$type}{{$key},\n" . implode(",\n", $body) . "\n}";
    }

    /** Converts one CSL-JSON reference or a list of them to BibTeX. */
    public static function toBibTeX(array $refs): string
    {
        $list = array_is_list($refs) && ($refs === [] || is_array($refs[0])) ? $refs : [$refs];
        $used = [];
        $out = [];
        foreach ($list as $ref) {
            $id = isset($ref['id']) ? (string) $ref['id'] : '';
            $key = $id !== '' && preg_match('/^[\w:.-]+$/', $id) ? $id : self::baseKey($ref);
            if (isset($used[$key])) {
                $n = 0;
                while (isset($used[$key . chr(97 + $n)])) {
                    $n++;
                }
                $key .= chr(97 + $n);
            }
            $used[$key] = true;
            $out[] = self::entry($ref, $key);
        }
        return implode("\n\n", $out);
    }

    // ------------------------------------------------------------- import

    /** Converts common LaTeX markup to plain Unicode text. */
    private static function cleanLatex(string $value): string
    {
        $s = (string) preg_replace_callback(
            '/\\\\([`\'^"~=.])\s*\{?\\\\?([A-Za-z])\}?/',
            static fn ($m) => $m[2] . self::ACCENTS[$m[1]],
            $value,
        );
        $s = (string) preg_replace_callback(
            '/\\\\([cvuH])\s*\{\\\\?([A-Za-z])\}/',
            static fn ($m) => $m[2] . self::ACCENTS[$m[1]],
            $s,
        );
        $s = str_replace('\\textbackslash{}', '\\', $s);
        $s = (string) preg_replace('/\\\\(?:emph|textit|textbf|textsc|mathrm)\{([^{}]*)\}/', '$1', $s);
        $s = (string) preg_replace('/\\\\([&%$#_{}])/', '$1', $s);
        $s = (string) preg_replace('/(?<!\\\\)[{}]/', '', $s);
        $s = str_replace('~', ' ', $s);
        $s = (string) preg_replace('/\s+/u', ' ', $s);
        if (class_exists(\Normalizer::class)) {
            $s = (string) \Normalizer::normalize($s, \Normalizer::FORM_C);
        }
        return trim($s);
    }

    /** @return list<string> */
    private static function splitTopLevel(string $value, string $separator): array
    {
        $parts = [];
        $depth = 0;
        $current = '';
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $c = $value[$i];
            if ($c === '{') {
                $depth++;
            }
            if ($c === '}') {
                $depth--;
            }
            if ($depth === 0 && preg_match($separator, substr($value, $i), $m)) {
                $parts[] = $current;
                $current = '';
                $i += strlen($m[0]) - 1;
                continue;
            }
            $current .= $c;
        }
        $parts[] = $current;
        return array_values(array_filter(array_map('trim', $parts), static fn ($p) => $p !== ''));
    }

    private static function parseNames(string $value): array
    {
        return array_map(static function (string $raw) {
            if (preg_match('/^\{[^{}]*\}$/', $raw)) {
                return ['literal' => self::cleanLatex(substr($raw, 1, -1))];
            }
            $parts = array_map([self::class, 'cleanLatex'], self::splitTopLevel($raw, '/^,/'));
            if (count($parts) === 3) {
                return ['family' => $parts[0], 'suffix' => $parts[1], 'given' => $parts[2]];
            }
            if (count($parts) === 2) {
                return ['family' => $parts[0], 'given' => $parts[1]];
            }
            return Names::nameParts(self::cleanLatex($raw));
        }, self::splitTopLevel($value, '/^\s+and\s+/i'));
    }

    private static function findClose(string $src, int $start, string $open, string $close): int
    {
        $depth = 0;
        $len = strlen($src);
        for ($i = $start; $i < $len; $i++) {
            if ($src[$i] === $open) {
                $depth++;
            } elseif ($src[$i] === $close) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return -1;
    }

    private static function parseFields(string $body, array $macros): array
    {
        $fields = [];
        $len = strlen($body);
        $i = 0;
        $isSpace = static fn (int $k) => $k < $len && ctype_space($body[$k]);

        while ($i < $len) {
            while ($i < $len && ($body[$i] === ',' || ctype_space($body[$i]))) {
                $i++;
            }
            if (!preg_match('/^[^\s=,{}]+/', substr($body, $i), $name)) {
                break;
            }
            $i += strlen($name[0]);
            while ($isSpace($i)) {
                $i++;
            }
            if ($i >= $len || $body[$i] !== '=') {
                break;
            }
            $i++;

            $value = '';
            while (true) {
                while ($isSpace($i)) {
                    $i++;
                }
                if ($i < $len && $body[$i] === '{') {
                    $end = self::findClose($body, $i, '{', '}');
                    if ($end < 0) {
                        break 2;
                    }
                    $value .= substr($body, $i + 1, $end - $i - 1);
                    $i = $end + 1;
                } elseif ($i < $len && $body[$i] === '"') {
                    $depth = 0;
                    $j = $i + 1;
                    for (; $j < $len; $j++) {
                        if ($body[$j] === '{') {
                            $depth++;
                        } elseif ($body[$j] === '}') {
                            $depth--;
                        } elseif ($body[$j] === '"' && $depth === 0 && $body[$j - 1] !== '\\') {
                            break;
                        }
                    }
                    $value .= substr($body, $i + 1, $j - $i - 1);
                    $i = $j + 1;
                } else {
                    if (!preg_match('/^[^\s,#}]+/', substr($body, $i), $token)) {
                        break;
                    }
                    $value .= $macros[strtolower($token[0])] ?? $token[0];
                    $i += strlen($token[0]);
                }
                while ($isSpace($i)) {
                    $i++;
                }
                if ($i >= $len || $body[$i] !== '#') {
                    break;
                }
                $i++;
            }
            $fields[strtolower($name[0])] = $value;
        }
        return $fields;
    }

    private static function toCSL(string $type, string $key, array $fields): array
    {
        $ref = ['id' => $key, 'type' => self::FROM_BIBTEX_TYPE[$type] ?? (!empty($fields['url']) ? 'webpage' : 'document')];
        $text = static fn (string $name) => isset($fields[$name]) && $fields[$name] !== '' ? self::cleanLatex($fields[$name]) : null;

        if (!empty($fields['author'])) {
            $ref['author'] = self::parseNames($fields['author']);
        }
        if (!empty($fields['editor'])) {
            $ref['editor'] = self::parseNames($fields['editor']);
        }
        if (!empty($fields['title'])) {
            $ref['title'] = $text('title');
        }
        $container = $text('journal') ?: $text('journaltitle') ?: $text('booktitle') ?: $text('howpublished');
        if ($container) {
            $ref['container-title'] = $container;
        }

        preg_match('/^(\d{4})(?:-(\d{1,2}))?(?:-(\d{1,2}))?/', $fields['date'] ?? '', $date);
        $year = $date[1] ?? (preg_match('/\d{4}/', $fields['year'] ?? '', $y) ? $y[0] : null);
        if ($year) {
            $month = !empty($date[2]) ? $date[2] : ($fields['month'] ?? null);
            if ($month && !ctype_digit((string) $month)) {
                $index = array_search(strtolower(substr(trim($month), 0, 3)), self::MONTH_NAMES, true);
                $month = $index === false ? 0 : $index + 1;
            }
            $parts = [(int) $year];
            if ((int) $month > 0) {
                $parts[] = (int) $month;
            }
            if (!empty($date[3])) {
                $parts[] = (int) $date[3];
            }
            $ref['issued'] = ['date-parts' => [$parts]];
        }

        if (!empty($fields['volume'])) {
            $ref['volume'] = $text('volume');
        }
        if (!empty($fields['number'])) {
            $ref['issue'] = $text('number');
        }
        if (!empty($fields['pages'])) {
            $ref['page'] = (string) preg_replace('/\s*-+\s*/', '-', (string) $text('pages'));
        }
        if (!empty($fields['edition'])) {
            $ref['edition'] = $text('edition');
        }
        $publisher = $text('publisher') ?: $text('school') ?: $text('institution') ?: $text('organization');
        if ($publisher) {
            $ref['publisher'] = $publisher;
        }
        if (!empty($fields['address']) || !empty($fields['location'])) {
            $ref['publisher-place'] = $text('address') ?: $text('location');
        }
        if (!empty($fields['isbn'])) {
            $ref['ISBN'] = $text('isbn');
        }
        if (!empty($fields['doi'])) {
            $ref['DOI'] = (string) preg_replace('~^https?://(dx\.)?doi\.org/~i', '', trim($fields['doi']));
        }
        if (!empty($fields['url'])) {
            $ref['URL'] = trim($fields['url']);
        }
        if ($type === 'phdthesis') {
            $ref['genre'] = 'Doctoral dissertation';
        }
        if ($type === 'mastersthesis') {
            $ref['genre'] = 'Master’s thesis';
        }
        return $ref;
    }

    /** Parses BibTeX/BibLaTeX text into a list of CSL-JSON references. */
    public static function parse(string $source): array
    {
        $macros = [];
        foreach (self::MONTH_NAMES as $i => $m) {
            $macros[$m] = (string) ($i + 1);
        }
        $refs = [];
        $i = 0;

        while (($at = strpos($source, '@', $i)) !== false) {
            if (!preg_match('/^@\s*([A-Za-z]+)\s*([{(])/', substr($source, $at), $head)) {
                $i = $at + 1;
                continue;
            }
            $type = strtolower($head[1]);
            $openIndex = $at + strlen($head[0]) - 1;
            $open = $head[2];
            $end = self::findClose($source, $openIndex, $open, $open === '{' ? '}' : ')');
            if ($end < 0) {
                break;
            }
            $body = substr($source, $openIndex + 1, $end - $openIndex - 1);
            $i = $end + 1;

            if ($type === 'comment' || $type === 'preamble') {
                continue;
            }
            if ($type === 'string') {
                $macros = array_merge($macros, self::parseFields($body, $macros));
                continue;
            }

            $comma = strpos($body, ',');
            $key = trim($comma === false ? $body : substr($body, 0, $comma));
            $fields = $comma === false ? [] : self::parseFields(substr($body, $comma + 1), $macros);
            $refs[] = self::toCSL($type, $key, $fields);
        }
        return $refs;
    }
}
