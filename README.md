# Plagx Cite for PHP

*A free academic writing tool by Plagiarism Checker X.*

**Plagx Cite for PHP is a free, open-source citation generator and reference checker for PHP and Laravel.** It turns sources into correct APA 7 and MLA 9 references and in-text citations, so your app can build a clean reference list in seconds. It also checks the list for duplicate references, broken DOIs and missing details.

Use it as an **APA citation generator** or **MLA citation generator**: create a **reference list, bibliography or works cited** page, generate **in-text citations**, turn a **DOI into a citation**, import and export **BibTeX**, and find **duplicate references**.

- No runtime dependencies, PHP 8.1+
- Works in any PHP project, including Laravel, Symfony and WordPress
- Uses [CSL-JSON](https://citeproc-js.readthedocs.io/en/latest/csl-json/markup.html), the reference format used by Zotero, Mendeley and Crossref
- Plain text, HTML or Markdown output
- Same output as the JavaScript version, [plagx-cite on npm](https://www.npmjs.com/package/plagx-cite)

**[Try the live demo →](https://plagiarismcheckerx.github.io/plagx-cite/)**

Developer downloads: [Packagist](https://packagist.org/packages/plagiarismcheckerx/plagx-cite) · [npm](https://www.npmjs.com/package/plagx-cite) · [PyPI](https://pypi.org/project/plagx-cite/) · [Docker Hub](https://hub.docker.com/r/plagiarismcheckerx/plagx-cite) · [GitHub](https://github.com/plagiarismcheckerx/plagx-cite)

Need a quick citation online? Use the [free citation generator](https://plagiarismcheckerx.com/citation-generator?utm_source=plagx-cite-php).

> Plagx Cite is a free academic writing utility by Plagiarism Checker X. It is a citation generator that builds and checks APA and MLA reference lists. Plagiarism checking and AI-generated text detection are available in Plagiarism Checker X, linked at the end of this page.

## Install

```bash
composer require plagiarismcheckerx/plagx-cite
```

## Quick start

```php
use Plagx\Cite\Cite;

$ref = [
    'type' => 'article-journal',
    'title' => 'Deep learning for text similarity',
    'author' => [['family' => 'Smith', 'given' => 'John Allen'], ['family' => 'Lee', 'given' => 'Kate']],
    'issued' => ['date-parts' => [[2020]]],
    'container-title' => 'Journal of Academic Writing',
    'volume' => '12',
    'issue' => '3',
    'page' => '45-67',
    'DOI' => '10.1234/jaw.2020.045',
];

Cite::formatReference($ref);
// Smith, J. A., & Lee, K. (2020). Deep learning for text similarity. Journal of Academic Writing, 12(3), 45–67. https://doi.org/10.1234/jaw.2020.045

Cite::formatReference($ref, ['style' => 'mla']);
// Smith, John Allen, and Kate Lee. “Deep learning for text similarity.” Journal of Academic Writing, vol. 12, no. 3, 2020, pp. 45-67, https://doi.org/10.1234/jaw.2020.045.

Cite::citeInText($ref, ['page' => '50']);                  // (Smith & Lee, 2020, p. 50)
Cite::citeInText($ref, ['narrative' => true]);             // Smith and Lee (2020)
Cite::citeInText($ref, ['style' => 'mla', 'page' => '50']); // (Smith and Lee 50)
```

References are plain PHP arrays in CSL-JSON form, so a Zotero or Crossref export works with `json_decode($json, true)`.

## API

All methods are static on `Plagx\Cite\Cite`.

### Formatting references and citations

| Method | Returns |
|---|---|
| `formatReference($ref, ['style' => …, 'format' => …])` | One reference-list entry |
| `formatBibliography($refs, ['style' => …, 'format' => …])` | Sorted list of entries |
| `citeInText($ref, ['style' => …, 'page' => …, 'narrative' => …])` | In-text citation |
| `sortReferences($refs)` | New list sorted by author, year, title |
| `styles()` | `['apa', 'mla']` |

- `style`: `'apa'` (default) or `'mla'`
- `format`: `'text'` (default), `'html'` (titles in `<i>`, text escaped) or `'markdown'` (titles in `*…*`)

Supported reference types: `article-journal`, `article-magazine`, `article-newspaper`, `book`, `chapter`, `paper-conference`, `report`, `thesis`, `webpage`. Other types fall back to a sensible general layout.

### BibTeX import and export

```php
Cite::toBibTeX([$ref]);
// @article{smith2020deep,
//   author = {Smith, John Allen and Lee, Kate},
//   ...

$refs = Cite::parseBibTeX(file_get_contents('library.bib'));
```

`parseBibTeX` handles `@string` macros, `#` concatenation, common LaTeX accents (`{\"u}` → ü), organisation names in braces, and BibLaTeX `date` / `journaltitle` fields.

### DOI lookup

```php
$ref = Cite::lookupDOI('10.1038/nature14539', ['mailto' => 'you@example.com']);
echo Cite::formatReference($ref);

Cite::normalizeDOI('https://doi.org/10.1038/NATURE14539'); // '10.1038/NATURE14539'
Cite::extractDOIs($text);                                  // every DOI found in the text
```

`lookupDOI` calls the free public [Crossref API](https://www.crossref.org/documentation/retrieve-metadata/rest-api/). If a DOI isn't registered with Crossref, such as arXiv or Zenodo DOIs from DataCite, it falls back to [doi.org content negotiation](https://citation.doi.org/). No API key is needed. It uses curl when available, otherwise PHP streams. To use your own HTTP client (for example Guzzle or Laravel's `Http`), pass `'http' => fn (string $url, array $headers) => ['status' => …, 'body' => …]`.

It throws `InvalidArgumentException` for an invalid DOI and `RuntimeException` when the DOI can't be found.

### Duplicate references

```php
Cite::findDuplicates($refs);
// [['indexes' => [0, 4], 'reasons' => ['doi']], ['indexes' => [2, 7], 'reasons' => ['title']]]
```

References count as duplicates when they share a DOI, or the same title and first author. Case, accents and punctuation are ignored.

### Checking a reference list

```php
$result = Cite::checkReferences($refs, ['checkDOIs' => true]);
// ['references' => 4, 'errors' => 2, 'warnings' => 1, 'issues' => [['level' => 'error', 'code' => 'duplicate', 'ref' => 'smith2020b', ...]]]
```

Errors are duplicates, invalid DOIs and DOIs that can't be found (with `checkDOIs`). Warnings are missing titles, authors or years.

## Laravel example

```php
use Plagx\Cite\Cite;

Route::post('/cite', function (Illuminate\Http\Request $request) {
    $ref = Cite::lookupDOI($request->input('doi'));

    return [
        'apa' => Cite::formatReference($ref),
        'mla' => Cite::formatReference($ref, ['style' => 'mla']),
        'in_text' => Cite::citeInText($ref),
    ];
});
```

## Good to know

- Titles are used exactly as you give them. APA wants sentence case for article and book titles, and MLA wants title case. Plagx Cite does not change capitalisation automatically, because doing so would break names and acronyms.
- The output follows the main APA 7 and MLA 9 rules for common reference types. Always check the final list against your institution's or publisher's guidelines.
- For the most accurate accent handling when matching duplicates, enable the `intl` PHP extension (most hosts have it).

## Development

```bash
composer install
composer test
```

<h2>Plagx Cite - Free Citation & Reference Toolkit</h2>

<p>
 An open-source citation utility for students, researchers and developers.
 Format references, organize citations and manage bibliographies.
 </p>

<p>
 Developed by Plagiarism Checker X.
 </p>

<p>
 Plagx Cite helps with source attribution; it does not detect plagiarism
 or AI-generated text. For full document checks, explore
 <a href="https://plagiarismcheckerx.com/?utm_source=plagx-cite-php">Plagiarism Checker X</a>
 and its
 <a href="https://plagiarismcheckerx.com/ai-content-detector?utm_source=plagx-cite-php">AI Content Detector</a>.
 </p>

## License

MIT © Plagiarism Checker X
