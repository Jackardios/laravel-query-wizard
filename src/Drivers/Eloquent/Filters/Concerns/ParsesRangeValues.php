<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Filters\Concerns;

trait ParsesRangeValues
{
    /**
     * Parse range value into [start, end] tuple
     *
     * Supports:
     * - Associative array: ['key1' => val1, 'key2' => val2]
     * - Indexed array: [val1, val2]
     *
     * @return array{0: mixed, 1: mixed}
     */
    protected function parseRangeValue(mixed $value, string $startKey, string $endKey): array
    {
        if (!is_array($value)) {
            return [null, null];
        }

        // Check for indexed array (comma-separated was parsed)
        if (array_is_list($value) && count($value) >= 2) {
            return [$value[0], $value[1]];
        }

        return [
            $value[$startKey] ?? null,
            $value[$endKey] ?? null,
        ];
    }
}
