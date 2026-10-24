<?php

namespace App\Modules\Shared\Seo;

use Illuminate\Support\Str;

/**
 * Title, description, canonical, robots and JSON-LD of one page.
 *
 * Limits from the implementation spec: title up to 60 characters, description
 * up to 155, both cut on a word boundary. The brand suffix goes first.
 */
final class Seo
{
    public const BRAND = 'MellowAura';

    public const TITLE_LIMIT = 60;

    public const DESCRIPTION_LIMIT = 155;

    /**
     * @param  array<int, array<string, mixed>>  $schema  JSON-LD nodes added to the Organization graph
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description = '',
        public readonly ?string $canonical = null,
        public readonly bool $indexable = true,
        public readonly array $schema = [],
    ) {}

    /**
     * A title written for the page, used as is when it fits.
     */
    public static function make(string $title, string $description = '', ?string $canonical = null): self
    {
        return new self(
            self::limit($title, self::TITLE_LIMIT),
            self::limit($description, self::DESCRIPTION_LIMIT),
            $canonical,
        );
    }

    /**
     * Builds "{base} — {tagline} | MellowAura"; when it is too long the brand
     * suffix goes first, then the tagline, then the base is cut on a word.
     */
    public static function titleFor(string $base, ?string $tagline = null): string
    {
        $candidates = array_filter([
            $tagline ? "{$base} — {$tagline} | ".self::BRAND : null,
            $tagline ? "{$base} — {$tagline}" : null,
            "{$base} | ".self::BRAND,
            $base,
        ]);

        foreach ($candidates as $candidate) {
            if (mb_strlen($candidate) <= self::TITLE_LIMIT) {
                return $candidate;
            }
        }

        return self::limit($base, self::TITLE_LIMIT);
    }

    /**
     * Cuts plain text on a word boundary, with "…" counted in the limit.
     */
    public static function limit(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        $lastSpace = mb_strrpos($cut, ' ');

        if ($lastSpace !== false && $lastSpace > $limit / 2) {
            $cut = mb_substr($cut, 0, $lastSpace);
        } else {
            $cut = mb_substr($cut, 0, $limit - 1);
        }

        return rtrim($cut, ' ,;:—–-').'…';
    }

    public function withSchema(array ...$nodes): self
    {
        return new self($this->title, $this->description, $this->canonical, $this->indexable, [...$this->schema, ...$nodes]);
    }

    public function noindex(): self
    {
        return new self($this->title, $this->description, $this->canonical, false, $this->schema);
    }

    public function canonicalUrl(): string
    {
        return $this->canonical ?? Str::before(url()->current(), '?');
    }
}
