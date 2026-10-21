<?php

declare(strict_types=1);

namespace Plagx\Cite;

/** @internal Escapes text and italicises titles for the chosen output format. */
final class Formatter
{
    private function __construct(private readonly string $format)
    {
    }

    public static function for(string $format): self
    {
        if (!in_array($format, ['text', 'html', 'markdown'], true)) {
            throw new \InvalidArgumentException("Unknown output format \"{$format}\". Use \"text\", \"html\" or \"markdown\".");
        }
        return new self($format);
    }

    /** Plain text in the output format. */
    public function t(string $s): string
    {
        return match ($this->format) {
            'html' => strtr($s, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;', '"' => '&quot;']),
            'markdown' => (string) preg_replace('/([\\\\*_])/', '\\\\$1', $s),
            default => $s,
        };
    }

    /** Italic text in the output format. */
    public function i(string $s): string
    {
        return match ($this->format) {
            'html' => '<i>' . $this->t($s) . '</i>',
            'markdown' => '*' . $this->t($s) . '*',
            default => $s,
        };
    }
}
