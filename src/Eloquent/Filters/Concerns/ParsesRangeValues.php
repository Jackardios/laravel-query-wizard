<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters\Concerns;

trait ParsesRangeValues
{
    /**
     * Parse range value into [start, end] tuple
     *
     * Supports:
     * - Associative array: ['key1' => val1, 'key2' => val2]
     * - Indexed array: [val1, val2]
     *
     * Empty strings are treated as null (no value).
     *
     * @return array{0: mixed, 1: mixed}
     */
    protected function parseRangeValue(mixed $value, string $startKey, string $endKey): array
    {
        if (! is_array($value)) {
            return [null, null];
        }

        if (array_is_list($value) && count($value) >= 2) {
            return [
                $this->normalizeRangeValue($value[0], $startKey),
                $this->normalizeRangeValue($value[1], $endKey),
            ];
        }

        return [
            $this->normalizeRangeValue($value[$startKey] ?? null, $startKey),
            $this->normalizeRangeValue($value[$endKey] ?? null, $endKey),
        ];
    }

    /**
     * Normalize range value - empty strings become null.
     * Subclasses can override to add type-specific validation.
     *
     * @param  string|null  $key  The request key of the bound, for error messages
     *
     * @api
     */
    protected function normalizeRangeValue(mixed $value, ?string $key = null): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return $value;
    }
}
