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
}
