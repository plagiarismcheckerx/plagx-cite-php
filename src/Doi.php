<?php

declare(strict_types=1);

namespace Plagx\Cite;

/** DOI normalising, extraction and lookup (Crossref, with a doi.org fallback). */
final class Doi
{
    private const PATTERN = '~10\.\d{4,9}/[^\s"\'<>]+~i';
    private const PREFIX = '~^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)~i';

    private const CROSSREF_TYPES = [
        'journal-article' => 'article-journal',
        'book' => 'book',
        'monograph' => 'book',
        'edited-book' => 'book',
        'reference-book' => 'book',
        'book-chapter' => 'chapter',
        'book-section' => 'chapter',
        'book-part' => 'chapter',
        'reference-entry' => 'entry-encyclopedia',
        'proceedings-article' => 'paper-conference',
        'report' => 'report',
        'dissertation' => 'thesis',
        'posted-content' => 'article',
        'dataset' => 'dataset',
    ];

    private const ENTITIES = ['amp' => '&', 'lt' => '<', 'gt' => '>', 'quot' => '"', 'apos' => "'", 'nbsp' => ' '];

    private static function trimTrailing(string $doi): string
    {
        $s = (string) preg_replace('/[.,;:]+$/', '', $doi);
        // Drop a closing bracket that has no opening partner inside the DOI.
        while (preg_match('/[)\]]$/', $s)) {
            $close = substr($s, -1);
            $open = $close === ')' ? '(' : '[';
            if (substr_count($s, $open) >= substr_count($s, $close)) {
                break;
            }
            $s = (string) preg_replace('/[.,;:]+$/', '', substr($s, 0, -1));
        }
        return $s;
    }

    /** Returns a bare DOI ("10.xxxx/...") from a DOI, doi: string or doi.org URL, or null if invalid. */
    public static function normalize(?string $input): ?string
    {
        $s = rawurldecode((string) preg_replace(self::PREFIX, '', trim((string) $input)));
        return preg_match('~^10\.\d{4,9}/\S+$~', $s) ? self::trimTrailing($s) : null;
    }

    public static function isDoi(?string $input): bool
    {
        return self::normalize($input) !== null;
    }

    /** Finds every DOI in a block of text (unique, in order of appearance). */
    public static function extract(?string $text): array
    {
        preg_match_all(self::PATTERN, (string) $text, $m);
        $found = [];
        foreach ($m[0] as $raw) {
            $doi = self::trimTrailing($raw);
            $found[strtolower($doi)] ??= $doi;
        }
        return array_values($found);
    }

    /** Metadata often contains HTML/JATS markup and entities; returns plain text. */
    private static function cleanText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $s = (string) preg_replace('/<[^>]+>/', '', (string) $value);
        $s = (string) preg_replace_callback('/&(#x[0-9a-f]+|#\d+|[a-z]+);/i', static function ($m) {
            $e = $m[1];
            if ($e[0] === '#') {
                $code = strtolower($e[1]) === 'x' ? hexdec(substr($e, 2)) : (int) substr($e, 1);
                return mb_chr((int) $code, 'UTF-8');
            }
            return self::ENTITIES[strtolower($e)] ?? $m[0];
        }, $s);
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));
        return $s === '' ? null : $s;
    }

    private static function cleanNames(mixed $list): ?array
    {
        if (!is_array($list) || $list === []) {
            return null;
        }
        return array_map(static fn ($a) => !empty($a['family'])
            ? ['family' => self::cleanText($a['family']), 'given' => self::cleanText($a['given'] ?? null) ?? '']
            : ['literal' => self::cleanText($a['name'] ?? $a['literal'] ?? null) ?? ''], array_values($list));
    }

    /** @return list<string> */
    private static function texts(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        return array_values(array_filter(array_map([self::class, 'cleanText'], $values), static fn ($v) => $v !== null));
    }

    private static function withSubtitle(?string $title, ?string $subtitle): ?string
    {
        if (!$title || !$subtitle || str_contains(mb_strtolower($title), mb_strtolower($subtitle))) {
            return $title;
        }
        return preg_match('/[:?!]$/', $title) ? "{$title} {$subtitle}" : "{$title}: {$subtitle}";
    }

    private static function dateParts(mixed $issued): ?array
    {
        $parts = $issued['date-parts'][0] ?? null;
        return is_array($parts) ? ['date-parts' => [array_values(array_filter($parts, static fn ($n) => $n !== null))]] : null;
    }

    private static function compact(array $ref): array
    {
        return array_filter($ref, static fn ($v) => $v !== null && $v !== '');
    }

    /** Maps a Crossref "work" record to CSL-JSON. */
    public static function fromCrossref(array $work): array
    {
        $type = self::CROSSREF_TYPES[$work['type'] ?? ''] ?? 'article';
        $issued = !empty($work['issued']['date-parts'][0][0]) ? $work['issued']
            : ($work['published-print'] ?? $work['published-online'] ?? $work['created'] ?? null);
        // For chapters and proceedings Crossref may list [series, book]; the book is last.
        $containers = self::texts($work['container-title'] ?? null);
        $ref = [
            'id' => $work['DOI'] ?? null,
            'type' => $type,
            'title' => self::withSubtitle(self::texts($work['title'] ?? null)[0] ?? null, self::texts($work['subtitle'] ?? null)[0] ?? null),
            'author' => self::cleanNames($work['author'] ?? null),
            'editor' => self::cleanNames($work['editor'] ?? null),
            'container-title' => in_array($type, ['chapter', 'paper-conference'], true) ? (end($containers) ?: null) : ($containers[0] ?? null),
            'issued' => self::dateParts($issued),
            'volume' => $work['volume'] ?? null,
            'issue' => $work['issue'] ?? null,
            'page' => $work['page'] ?? null,
            'publisher' => self::cleanText($work['publisher'] ?? null),
            'publisher-place' => self::cleanText($work['publisher-location'] ?? null),
            'ISBN' => self::texts($work['ISBN'] ?? null)[0] ?? null,
            'DOI' => $work['DOI'] ?? null,
            'URL' => $work['URL'] ?? null,
        ];
        if ($type === 'thesis' && !empty($work['institution'][0]['name'])) {
            $ref['publisher'] = self::cleanText($work['institution'][0]['name']);
        }
        return self::compact($ref);
    }

    /** Keeps the CSL-JSON fields Plagx Cite uses from doi.org content negotiation (DataCite, arXiv, mEDRA…). */
    private static function fromCSL(array $data): array
    {
        return self::compact([
            'id' => $data['DOI'] ?? null,
            'type' => $data['type'] ?? 'article',
            'title' => self::texts($data['title'] ?? null)[0] ?? null,
            'author' => self::cleanNames($data['author'] ?? null),
            'editor' => self::cleanNames($data['editor'] ?? null),
            'container-title' => self::texts($data['container-title'] ?? null)[0] ?? null,
            'issued' => self::dateParts($data['issued'] ?? null),
            'volume' => $data['volume'] ?? null,
            'issue' => $data['issue'] ?? null,
            'page' => $data['page'] ?? null,
            'publisher' => self::cleanText($data['publisher'] ?? null),
            'DOI' => $data['DOI'] ?? null,
            'URL' => $data['URL'] ?? null,
        ]);
    }

    /** Same character set as JavaScript's encodeURI(). */
    private static function encodeUri(string $s): string
    {
        return (string) preg_replace_callback(
            "~[^A-Za-z0-9;,/?:@&=+\$\\-_.!\\~*'()#]~",
            static fn ($m) => rawurlencode($m[0]),
            $s,
        );
    }

    /**
     * Looks up a DOI and returns CSL-JSON. Tries the public Crossref API first, then
     * doi.org content negotiation (covers DataCite DOIs such as arXiv and Zenodo).
     *
     * Options:
     * - mailto: email for Crossref's polite pool (optional)
     * - http:   callable(string $url, array $headers): array{status: int, body: string}
     *
     * @throws \InvalidArgumentException for an invalid DOI
     * @throws \RuntimeException when the DOI can't be found or the lookup fails
     */
    public static function lookup(string $input, array $options = []): array
    {
        $doi = self::normalize($input);
        if ($doi === null) {
            throw new \InvalidArgumentException("Not a valid DOI: \"{$input}\"");
        }
        $http = $options['http'] ?? [self::class, 'httpGet'];

        $url = 'https://api.crossref.org/works/' . rawurlencode($doi);
        if (!empty($options['mailto'])) {
            $url .= '?mailto=' . rawurlencode($options['mailto']);
        }
        $res = $http($url, ['Accept: application/json']);
        if ($res['status'] >= 200 && $res['status'] < 300) {
            return self::fromCrossref(json_decode($res['body'], true)['message'] ?? []);
        }
        if ($res['status'] !== 404) {
            throw new \RuntimeException("DOI lookup failed ({$res['status']}) for {$doi}");
        }

        $fallback = $http('https://doi.org/' . self::encodeUri($doi), ['Accept: application/vnd.citationstyles.csl+json']);
        if ($fallback['status'] === 404) {
            throw new \RuntimeException("DOI not found: {$doi}");
        }
        if ($fallback['status'] < 200 || $fallback['status'] >= 300) {
            throw new \RuntimeException("DOI lookup failed ({$fallback['status']}) for {$doi}");
        }
        return self::fromCSL(json_decode($fallback['body'], true) ?? []);
    }

    /**
     * @internal Default HTTP GET (curl if available, otherwise PHP streams); follows redirects.
     * @return array{status: int, body: string}
     */
    public static function httpGet(string $url, array $headers): array
    {
        $headers[] = 'User-Agent: plagx-cite-php (+https://github.com/plagiarismcheckerx/plagx-cite-php)';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($body === false) {
                throw new \RuntimeException("DOI lookup failed: {$error}");
            }
            return ['status' => $status, 'body' => (string) $body];
        }

        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'follow_location' => 1,
            'timeout' => 20,
        ]]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $m)) {
                $status = (int) $m[1];
            }
        }
        if ($body === false && $status === 0) {
            throw new \RuntimeException('DOI lookup failed: no network connection');
        }
        return ['status' => $status, 'body' => (string) $body];
    }
}
