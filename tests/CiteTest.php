<?php

declare(strict_types=1);

namespace Plagx\Cite\Tests;

use PHPUnit\Framework\TestCase;
use Plagx\Cite\Cite;

/** Same cases and expected output as the JavaScript package (plagx-cite on npm). */
final class CiteTest extends TestCase
{
    private const ARTICLE = [
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

    private const BOOK = [
        'type' => 'book',
        'title' => 'Writing research papers',
        'author' => [['family' => 'Brown', 'given' => 'Mary']],
        'issued' => ['date-parts' => [[2018]]],
        'publisher' => 'Academic Press',
        'edition' => '2',
    ];

    private const CHAPTER = [
        'type' => 'chapter',
        'title' => 'Citing sources well',
        'author' => [['family' => 'Khan', 'given' => 'Ali'], ['family' => 'Ng', 'given' => 'Wei'], ['family' => 'Ruiz', 'given' => 'Ana']],
        'editor' => [['family' => 'Green', 'given' => 'Paul'], ['family' => 'White', 'given' => 'Sara Jane']],
        'container-title' => 'Handbook of scholarly writing',
        'page' => '101-120',
        'publisher' => 'Routledge',
        'issued' => ['date-parts' => [[2019]]],
    ];

    private const WEBPAGE = [
        'type' => 'webpage',
        'title' => 'How to avoid plagiarism',
        'author' => [['literal' => 'Writing Centre']],
        'container-title' => 'University Library',
        'issued' => ['date-parts' => [[2023, 3, 15]]],
        'URL' => 'https://example.com/post',
    ];

    public function testApaReferenceListEntries(): void
    {
        $this->assertSame(
            'Smith, J. A., & Lee, K. (2020). Deep learning for text similarity. Journal of Academic Writing, 12(3), 45–67. https://doi.org/10.1234/jaw.2020.045',
            Cite::formatReference(self::ARTICLE),
        );
        $this->assertSame('Brown, M. (2018). Writing research papers (2nd ed.). Academic Press.', Cite::formatReference(self::BOOK));
        $this->assertSame(
            'Khan, A., Ng, W., & Ruiz, A. (2019). Citing sources well. In P. Green & S. J. White (Eds.), Handbook of scholarly writing (pp. 101–120). Routledge.',
            Cite::formatReference(self::CHAPTER),
        );
        $this->assertSame(
            'Writing Centre. (2023, March 15). How to avoid plagiarism. University Library. https://example.com/post',
            Cite::formatReference(self::WEBPAGE),
        );
    }

    public function testApaWithoutAuthorOrDate(): void
    {
        $this->assertSame(
            'Citation basics. (n.d.). https://example.com',
            Cite::formatReference(['type' => 'webpage', 'title' => 'Citation basics', 'URL' => 'https://example.com']),
        );
    }

    public function testApaListsManyAuthorsWithEllipsis(): void
    {
        $author = array_map(static fn ($i) => ['family' => 'A' . ($i + 1), 'given' => 'B'], range(0, 21));
        $out = Cite::formatReference(['type' => 'article-journal', 'title' => 'T', 'author' => $author, 'issued' => ['date-parts' => [[2021]]]]);
        $this->assertMatchesRegularExpression('/^A1, B\., .*A19, B\., \. \. \. A22, B\. \(2021\)/', $out);
        $this->assertStringNotContainsString('A20', $out);
    }

    public function testMlaWorksCitedEntries(): void
    {
        $mla = ['style' => 'mla'];
        $this->assertSame(
            'Smith, John Allen, and Kate Lee. “Deep learning for text similarity.” Journal of Academic Writing, vol. 12, no. 3, 2020, pp. 45-67, https://doi.org/10.1234/jaw.2020.045.',
            Cite::formatReference(self::ARTICLE, $mla),
        );
        $this->assertSame('Brown, Mary. Writing research papers. 2nd ed., Academic Press, 2018.', Cite::formatReference(self::BOOK, $mla));
        $this->assertSame(
            'Khan, Ali, et al. “Citing sources well.” Handbook of scholarly writing, edited by Paul Green and Sara Jane White, Routledge, 2019, pp. 101-120.',
            Cite::formatReference(self::CHAPTER, $mla),
        );
        $this->assertSame(
            'Writing Centre. “How to avoid plagiarism.” University Library, 15 Mar. 2023, https://example.com/post.',
            Cite::formatReference(self::WEBPAGE, $mla),
        );
    }

    public function testHtmlAndMarkdownOutput(): void
    {
        $this->assertSame('Brown, M. (2018). <i>Writing research papers</i> (2nd ed.). Academic Press.', Cite::formatReference(self::BOOK, ['format' => 'html']));
        $this->assertSame('Brown, M. (2018). *Writing research papers* (2nd ed.). Academic Press.', Cite::formatReference(self::BOOK, ['format' => 'markdown']));
        $this->assertStringContainsString('<i>Tom &amp; &lt;Jerry&gt;</i>', Cite::formatReference(['title' => 'Tom & <Jerry>'] + self::BOOK, ['format' => 'html']));
    }

    public function testInTextCitations(): void
    {
        $this->assertSame('(Smith & Lee, 2020, p. 50)', Cite::citeInText(self::ARTICLE, ['page' => '50']));
        $this->assertSame('Smith and Lee (2020)', Cite::citeInText(self::ARTICLE, ['narrative' => true]));
        $this->assertSame('(Khan et al., 2019, pp. 101–103)', Cite::citeInText(self::CHAPTER, ['page' => '101-103']));
        $this->assertSame('(Smith and Lee 50)', Cite::citeInText(self::ARTICLE, ['style' => 'mla', 'page' => '50']));
        $this->assertSame('(Khan et al.)', Cite::citeInText(self::CHAPTER, ['style' => 'mla']));
        $this->assertSame('(“Citation guide,” 2021)', Cite::citeInText(['title' => 'Citation guide', 'year' => 2021]));
        $this->assertSame('“Citation guide” (n.d.)', Cite::citeInText(['title' => 'Citation guide'], ['narrative' => true]));
    }

    public function testBibliographyIsSorted(): void
    {
        $out = Cite::formatBibliography([self::ARTICLE, self::CHAPTER, self::BOOK]);
        $this->assertSame(['Brown', 'Khan', 'Smith'], array_map(static fn ($s) => explode(',', $s)[0], $out));
    }

    public function testUnknownStyleOrFormat(): void
    {
        $this->expectExceptionMessageMatches('/Unknown citation style/');
        Cite::formatReference(self::BOOK, ['style' => 'xyz']);
    }

    public function testUnknownFormat(): void
    {
        $this->expectExceptionMessageMatches('/Unknown output format/');
        Cite::formatReference(self::BOOK, ['format' => 'pdf']);
    }

    public function testInvalidDoiIsNotALinkAndFullUrlIsNotDoubled(): void
    {
        $base = ['type' => 'book', 'title' => 'T', 'author' => [['family' => 'A', 'given' => 'B']], 'year' => 2001];
        $this->assertSame('A, B. (2001). T.', Cite::formatReference($base + ['DOI' => 'not-a-doi']));
        $this->assertSame('A, B. (2001). T. https://doi.org/10.1234/x', Cite::formatReference($base + ['DOI' => 'https://doi.org/10.1234/x']));
    }

    public function testBibTeXExport(): void
    {
        $this->assertSame(implode("\n", [
            '@article{smith2020deep,',
            '  author = {Smith, John Allen and Lee, Kate},',
            '  title = {Deep learning for text similarity},',
            '  journal = {Journal of Academic Writing},',
            '  year = {2020},',
            '  volume = {12},',
            '  number = {3},',
            '  pages = {45--67},',
            '  doi = {10.1234/jaw.2020.045}',
            '}',
        ]), Cite::toBibTeX(self::ARTICLE));
        $this->assertStringContainsString('title = {50\\% of R\\&D}', Cite::toBibTeX(['title' => '50% of R&D'] + self::BOOK));
        preg_match_all('/@book\{(\w+),/', Cite::toBibTeX([self::BOOK, self::BOOK]), $m);
        $this->assertSame(['brown2018writing', 'brown2018writinga'], $m[1]);
    }

    public function testBibTeXRoundTrip(): void
    {
        $parsed = Cite::parseBibTeX(Cite::toBibTeX([self::ARTICLE, self::BOOK, self::CHAPTER]));
        foreach ([self::ARTICLE, self::BOOK, self::CHAPTER] as $i => $original) {
            $ref = $parsed[$i];
            unset($ref['id']);
            $this->assertEquals($original, $ref);
        }
    }

    public function testBibTeXImportHandlesLatexMacrosAndOrganisations(): void
    {
        [$ref] = Cite::parseBibTeX(<<<'BIB'
            @comment{ignored}
            @string{jaw = "Journal of Academic Writing"}
            @Article{muller21,
              author  = {M{\"u}ller, J{\"o}rg and {World Health Organization}},
              title   = "{T}he {\&} problem",
              journal = jaw,
              year    = 2021, month = mar,
              pages   = "1--9",
              doi     = {https://doi.org/10.5555/abc}
            }
            BIB);
        $this->assertSame([['family' => 'Müller', 'given' => 'Jörg'], ['literal' => 'World Health Organization']], $ref['author']);
        $this->assertSame('The & problem', $ref['title']);
        $this->assertSame('Journal of Academic Writing', $ref['container-title']);
        $this->assertSame(['date-parts' => [[2021, 3]]], $ref['issued']);
        $this->assertSame('1-9', $ref['page']);
        $this->assertSame('10.5555/abc', $ref['DOI']);
    }

    public function testDoiNormalisingAndExtraction(): void
    {
        $this->assertSame('10.1234/ABC.5', Cite::normalizeDOI('https://doi.org/10.1234/ABC.5'));
        $this->assertSame('10.1234/abc', Cite::normalizeDOI('doi: 10.1234/abc'));
        $this->assertNull(Cite::normalizeDOI('not a doi'));
        $this->assertSame(
            ['10.1000/xyz123', '10.5555/a(b)c'],
            Cite::extractDOIs('See (doi:10.1000/xyz123). Also https://doi.org/10.1000/xyz123 and 10.5555/a(b)c.'),
        );
    }

    public function testDoiLookupMapsCrossref(): void
    {
        $http = function (string $url, array $headers): array {
            $this->assertSame('https://api.crossref.org/works/10.1234%2Fjaw.2020.045?mailto=a%40b.org', $url);
            return ['status' => 200, 'body' => json_encode(['message' => [
                'type' => 'journal-article',
                'title' => ['Deep learning for text similarity'],
                'author' => [['given' => 'John Allen', 'family' => 'Smith'], ['given' => 'Kate', 'family' => 'Lee']],
                'container-title' => ['Journal of Academic Writing'],
                'issued' => ['date-parts' => [[2020]]],
                'volume' => '12',
                'issue' => '3',
                'page' => '45-67',
                'DOI' => '10.1234/jaw.2020.045',
                'URL' => 'https://doi.org/10.1234/jaw.2020.045',
                'publisher' => 'Example Publisher',
            ]])];
        };
        $ref = Cite::lookupDOI('doi:10.1234/jaw.2020.045', ['http' => $http, 'mailto' => 'a@b.org']);
        $this->assertSame(Cite::formatReference(self::ARTICLE), Cite::formatReference($ref));
    }

    public function testDoiLookupErrors(): void
    {
        $notFound = static fn () => ['status' => 404, 'body' => ''];
        try {
            Cite::lookupDOI('10.1234/none', ['http' => $notFound]);
            $this->fail('expected an exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('DOI not found', $e->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        Cite::lookupDOI('nope', ['http' => $notFound]);
    }

    public function testDoiLookupFallsBackToDoiOrg(): void
    {
        $calls = [];
        $http = static function (string $url, array $headers) use (&$calls): array {
            $calls[] = [$url, $headers[0]];
            if (str_starts_with($url, 'https://api.crossref.org/')) {
                return ['status' => 404, 'body' => ''];
            }
            return ['status' => 200, 'body' => json_encode([
                'type' => 'article',
                'title' => 'Attention is all you need',
                'author' => [['family' => 'Vaswani', 'given' => 'Ashish'], ['family' => 'Shazeer', 'given' => 'Noam']],
                'issued' => ['date-parts' => [[2017]]],
                'publisher' => 'arXiv',
                'DOI' => '10.48550/ARXIV.1706.03762',
                'abstract' => 'ignored',
            ])];
        };
        $ref = Cite::lookupDOI('10.48550/arXiv.1706.03762', ['http' => $http]);
        $this->assertSame(['https://doi.org/10.48550/arXiv.1706.03762', 'Accept: application/vnd.citationstyles.csl+json'], $calls[1]);
        $this->assertArrayNotHasKey('abstract', $ref);
        $this->assertSame(
            'Vaswani, A., & Shazeer, N. (2017). Attention is all you need. arXiv. https://doi.org/10.48550/ARXIV.1706.03762',
            Cite::formatReference($ref),
        );
        $this->assertSame(
            'Vaswani, Ashish, and Noam Shazeer. “Attention is all you need.” arXiv, 2017, https://doi.org/10.48550/ARXIV.1706.03762.',
            Cite::formatReference($ref, ['style' => 'mla']),
        );
    }

    public function testCrossrefMarkupSubtitleAndBookTitle(): void
    {
        $ref = Cite::fromCrossref([
            'type' => 'book-chapter',
            'title' => ['Optuna'],
            'subtitle' => ['A next-generation <i>hyperparameter</i> framework'],
            'container-title' => ['Lecture Notes in Computer Science', 'Knowledge Discovery &amp; Data Mining'],
            'author' => [['family' => 'Akiba', 'given' => 'Takuya']],
            'publisher' => 'ACM',
            'DOI' => '10.1145/1',
        ]);
        $this->assertSame('Optuna: A next-generation hyperparameter framework', $ref['title']);
        $this->assertSame('Knowledge Discovery & Data Mining', $ref['container-title']);
        $this->assertSame('Nature', Cite::fromCrossref(['type' => 'journal-article', 'container-title' => ['Nature', 'Nat.']])['container-title']);
    }

    public function testDuplicateDetection(): void
    {
        $groups = Cite::findDuplicates([
            self::ARTICLE,
            self::BOOK,
            ['title' => 'A different title', 'DOI' => 'https://doi.org/10.1234/JAW.2020.045'] + self::ARTICLE,
            ['title' => 'Writing Research Papers!'] + self::BOOK,
            ['author' => [['family' => 'Someone', 'given' => 'Else']]] + self::BOOK,
        ]);
        $this->assertSame([
            ['indexes' => [0, 2], 'reasons' => ['doi']],
            ['indexes' => [1, 3], 'reasons' => ['title']],
        ], $groups);
        $this->assertSame([], Cite::findDuplicates([self::ARTICLE, self::BOOK]));
    }

    public function testCheckReferences(): void
    {
        $refs = Cite::parseBibTeX(<<<'BIB'
            @article{smith2020, author={Smith, John and Lee, Kate}, title={Deep learning for text similarity},
              journal={Journal of Academic Writing}, year=2020, volume=12, number=3, pages={45--67}}
            @book{brown2018, author={Brown, Mary}, title={Writing research papers}, publisher={Academic Press}, year=2018}
            @article{smithdup, author={Smith, J.}, title={Deep Learning for Text Similarity!}, journal={JAW}, year=2020}
            @misc{bad, title={Untitled web page}, doi={not-a-doi}}
            BIB);
        $result = Cite::checkReferences($refs);
        $this->assertSame(4, $result['references']);
        $this->assertSame(2, $result['errors']);
        $this->assertSame(2, $result['warnings']);
        $this->assertSame(['duplicate', 'invalid-doi', 'missing-author', 'missing-year'], array_column($result['issues'], 'code'));
        $this->assertSame('Possible duplicate (title match) of smith2020: smith2020, smithdup', $result['issues'][0]['message']);

        $missing = Cite::checkReferences(
            [['title' => 'X', 'author' => ['A B'], 'year' => 2000, 'DOI' => '10.1234/missing']],
            ['checkDOIs' => true, 'http' => static fn () => ['status' => 404, 'body' => '']],
        );
        $this->assertSame(['doi-not-found'], array_column($missing['issues'], 'code'));
    }
}
