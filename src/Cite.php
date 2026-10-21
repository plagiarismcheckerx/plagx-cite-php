<?php

declare(strict_types=1);

namespace Plagx\Cite;

use Plagx\Cite\Styles\Apa;
use Plagx\Cite\Styles\Mla;

/**
 * Plagx Cite: a free academic writing utility by Plagiarism Checker X.
 * https://plagiarismcheckerx.com/ · MIT License
 *
 * References are CSL-JSON arrays, e.g. json_decode($json, true).
 */
final class Cite
{
    private const STYLES = ['apa' => Apa::class, 'mla' => Mla::class];

    /** Names of the built-in citation styles. */
    public static function styles(): array
    {
        return array_keys(self::STYLES);
    }

    /** @return class-string<Apa>|class-string<Mla> */
    private static function style(string $name): string
    {
        $style = self::STYLES[strtolower($name)] ?? null;
        if ($style === null) {
            throw new \InvalidArgumentException("Unknown citation style \"{$name}\". Available: " . implode(', ', self::styles()) . '.');
        }
        return $style;
    }

    /**
     * Formats one reference as a reference-list entry.
     * Options: style ('apa' | 'mla', default 'apa'), format ('text' | 'html' | 'markdown', default 'text').
     */
    public static function formatReference(array $ref, array $options = []): string
    {
        $style = self::style($options['style'] ?? 'apa');
        return $style::reference($ref, Formatter::for($options['format'] ?? 'text'));
    }

    /** Sorts references and formats each one; returns a list of entries. */
    public static function formatBibliography(array $refs, array $options = []): array
    {
        return array_map(static fn ($ref) => self::formatReference($ref, $options), self::sortReferences($refs));
    }

    /**
     * In-text citation, e.g. "(Smith & Lee, 2020, p. 4)" or "Smith and Lee (2020)".
     * Options: style, page (e.g. '12' or '12-15'), narrative (bool).
     */
    public static function citeInText(array $ref, array $options = []): string
    {
        $style = self::style($options['style'] ?? 'apa');
        return $style::inText($ref, $options['page'] ?? null, (bool) ($options['narrative'] ?? false));
    }

    private static function sortKey(array $ref): string
    {
        $lead = !empty($ref['author'])
            ? Names::familyName(array_values($ref['author'])[0])
            : (string) preg_replace('/^(a|an|the)\s+/i', '', (string) ($ref['title'] ?? ''));
        return Util::normalizeText($lead) . ' ' . (Util::getYear($ref) ?? '0000') . ' ' . Util::normalizeText($ref['title'] ?? '');
    }

    /** Returns a new list sorted by first author (or title), year, then title. */
    public static function sortReferences(array $refs): array
    {
        $refs = array_values($refs);
        $keys = array_map([self::class, 'sortKey'], $refs);
        $order = array_keys($refs);
        usort($order, static fn ($a, $b) => strcmp($keys[$a], $keys[$b]) ?: $a <=> $b);
        return array_map(static fn ($i) => $refs[$i], $order);
    }

    /** Converts one reference or a list of references to BibTeX. */
    public static function toBibTeX(array $refs): string
    {
        return BibTeX::toBibTeX($refs);
    }

    /** Parses BibTeX/BibLaTeX text into a list of CSL-JSON references. */
    public static function parseBibTeX(string $source): array
    {
        return BibTeX::parse($source);
    }

    public static function normalizeDOI(?string $input): ?string
    {
        return Doi::normalize($input);
    }

    public static function isDOI(?string $input): bool
    {
        return Doi::isDoi($input);
    }

    public static function extractDOIs(?string $text): array
    {
        return Doi::extract($text);
    }

    /** Looks up a DOI online and returns CSL-JSON. See Doi::lookup() for options. */
    public static function lookupDOI(string $doi, array $options = []): array
    {
        return Doi::lookup($doi, $options);
    }

    /** Maps a Crossref "work" record to CSL-JSON. */
    public static function fromCrossref(array $work): array
    {
        return Doi::fromCrossref($work);
    }

    /** @return list<array{indexes: list<int>, reasons: list<string>}> */
    public static function findDuplicates(array $refs): array
    {
        return Duplicates::find($refs);
    }

    /** Checks a reference list. See Checker::check() for options and the result shape. */
    public static function checkReferences(array $refs, array $options = []): array
    {
        return Checker::check($refs, $options);
    }
}
