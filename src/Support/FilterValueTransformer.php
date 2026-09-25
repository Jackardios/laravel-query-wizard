<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Illuminate\Support\Str;

/**
 * Transforms filter values from request data.
 *
 * Handles:
 * - Empty string to null conversion (filter not applied)
 * - Comma-separated string to array conversion (unless splitting is disabled)
 * - Recursive array transformation
 *
 * @internal
 */
final class FilterValueTransformer
{
    public function __construct(
        private readonly string $arraySeparator = ','
    ) {}

    /**
     * Transform a filter value.
     *
     * With $split = false, strings are kept whole (only '' becomes null), for
     * filters whose value is free text that may contain the separator.
     */
    public function transform(mixed $value, bool $split = true): mixed
    {
        if (is_array($value)) {
            return $this->transformArray($value, $split);
        }

        if (is_string($value)) {
            return $this->transformString($value, $split);
        }

        return $value;
    }

    /**
     * Transform an array of filter values recursively.
     *
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private function transformArray(array $values, bool $split): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[$key] = $this->transform($value, $split);
        }

        return $result;
    }

    /**
     * Transform a string filter value.
     *
     * - Empty string → null (filter not applied)
     * - 'a,b,c' → ['a', 'b', 'c'] when splitting is enabled
     */
    private function transformString(string $value, bool $split): mixed
    {
        if ($value === '') {
            return null;
        }

        if ($split && $this->arraySeparator !== '' && Str::contains($value, $this->arraySeparator)) {
            return $this->transformArray($this->splitToArray($value), $split);
        }

        return $value;
    }

    /**
     * Split a string into array by separator.
     *
     * Empty strings are filtered out from the result.
     * If all values are empty, returns empty array.
     *
     * @return array<int, string>
     */
    private function splitToArray(string $value): array
    {
        if ($this->arraySeparator === '') {
            return [$value];
        }

        $parts = array_filter(
            array_map('trim', explode($this->arraySeparator, $value)),
            static fn ($v) => $v !== ''
        );

        return array_values($parts);
    }
}
