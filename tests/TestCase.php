<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Collection;

abstract class TestCase extends BaseTestCase
{
    /**
     * The JSON-LD blocks of a rendered page, decoded.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function structuredData(string $html): Collection
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        return collect($matches[1])->map(fn (string $json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR));
    }
}
