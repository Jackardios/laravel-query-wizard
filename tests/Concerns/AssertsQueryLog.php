<?php

namespace Jackardios\QueryWizard\Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Tests\TestCase;

/**
 * @mixin TestCase
 */
trait AssertsQueryLog
{
    /**
     * Normalize SQL quotes for cross-database comparison.
     * Converts backticks (MySQL) to double quotes (SQLite) for consistent matching.
     */
    protected function normalizeQuotes(string $sql): string
    {
        return str_replace('`', '"', $sql);
    }

    protected function assertQueryLogContains(string $partialSql): void
    {
        $queryLog = collect(DB::getQueryLog())->pluck('query')->implode('|');

        $normalizedLog = $this->normalizeQuotes($queryLog);
        $normalizedPartial = $this->normalizeQuotes($partialSql);

        $this->assertTrue(
            Str::contains($normalizedLog, $normalizedPartial),
            "Query log does not contain: {$partialSql}\nActual queries: {$queryLog}"
        );
    }

    protected function assertQueryLogDoesntContain(string $partialSql): void
    {
        $queryLog = collect(DB::getQueryLog())->pluck('query')->implode('|');

        $normalizedLog = $this->normalizeQuotes($queryLog);
        $normalizedPartial = $this->normalizeQuotes($partialSql);

        $this->assertFalse(
            Str::contains($normalizedLog, $normalizedPartial),
            "Query log contained partial SQL: `{$partialSql}`"
        );
    }

    protected function assertQueryExecuted(string $query): void
    {
        $queries = array_map(function ($queryLogItem) {
            return $this->normalizeQuotes($queryLogItem['query']);
        }, DB::getQueryLog());

        $this->assertContains($this->normalizeQuotes($query), $queries);
    }

    /**
     * Assert SQL strings are equal after normalizing quotes.
     */
    protected function assertSqlEquals(string $expected, string $actual): void
    {
        $this->assertSame(
            $this->normalizeQuotes($expected),
            $this->normalizeQuotes($actual)
        );
    }

    /**
     * Assert SQL string contains partial SQL after normalizing quotes.
     */
    protected function assertSqlContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            Str::contains($this->normalizeQuotes($haystack), $this->normalizeQuotes($needle)),
            "SQL does not contain: {$needle}\nActual SQL: {$haystack}"
        );
    }
}
