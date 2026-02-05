<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Illuminate\Support\Str;

/**
 * Transforms filter values from request data.
 *
 * Handles:
 * - Boolean string conversion ('true' -> true, 'false' -> false)
 * - Comma-separated string to array conversion
 * - Recursive array transformation
 */
final class FilterValueTransformer
{
    public function __construct(
        private readonly string $arraySeparator = ','
    ) {}

    /**
     * Transform a filter value.
     */
    public function transform(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->transformArray($value);
        }

        if (is_string($value)) {
            return $this->transformString($value);
        }

        return $value;
    }

    /**
     * Transform an array of filter values recursively.
     *
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private function transformArray(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[$key] = $this->transform($value);
        }

        return $result;
    }

    /**
     * Transform a string filter value.
     *
     * - Empty string → null (filter not applied)
     * - 'true' → true
     * - 'false' → false
     * - 'a,b,c' → ['a', 'b', 'c']
     */
    private function transformString(string $value): mixed
    {
        // Empty string is treated as "no value" (null)
        if ($value === '') {
            return null;
        }

        // Check for comma-separated values first
        if ($this->arraySeparator !== '' && Str::contains($value, $this->arraySeparator)) {
            return $this->splitToArray($value);
        }

        // Check for boolean strings
        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        return $value;
    }

    /**
     * Split a string into array by separator.
     *
     * Empty strings are filtered out from the result.
     * If all values are empty, returns null instead of empty array.
     *
     * @return array<int, string>|null
     */
    private function splitToArray(string $value): ?array
    {
        if ($this->arraySeparator === '') {
            return [$value];
        }

        $parts = array_filter(
            array_map('trim', explode($this->arraySeparator, $value)),
            static fn ($v) => $v !== ''
        );

        // Return null if all parts were empty (e.g., ",,," → null)
        return empty($parts) ? null : array_values($parts);
    }
}
