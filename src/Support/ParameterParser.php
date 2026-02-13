<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Values\Sort;

/**
 * Parses request parameters into structured formats.
 *
 * Handles:
 * - Comma-separated string to array conversion
 * - Fields string parsing with dot notation grouping
 * - Sort value parsing with direction detection
 */
final class ParameterParser
{
    public function __construct(
        private readonly string $arraySeparator = ','
    ) {}

    /**
     * Parse a list parameter (includes, appends, etc.) into a collection.
     *
     * @return Collection<int, string>
     */
    public function parseList(mixed $value): Collection
    {
        if (is_string($value)) {
            $value = $this->splitString($value);
        }

        if (! is_iterable($value)) {
            return collect();
        }

        /** @var Collection<int, string> */
        return collect($value)
            ->map(function (mixed $item): ?string {
                if (is_string($item)) {
                    return trim($item);
                }

                if (is_int($item) || is_float($item)) {
                    return (string) $item;
                }

                return null;
            })
            ->filter(static fn (?string $item): bool => $item !== null && $item !== '')
            ->unique()
            ->values();
    }

    /**
     * Parse sorts parameter into Sort value objects.
     *
     * @return Collection<int, Sort>
     */
    public function parseSorts(mixed $value): Collection
    {
        if (is_string($value)) {
            $value = $this->splitString($value);
        }

        if (! is_iterable($value)) {
            return collect();
        }

        return collect($value)
            ->map(function (mixed $field): ?string {
                if (is_string($field)) {
                    $field = trim($field);

                    return $field !== '' ? $field : null;
                }

                if (is_int($field) || is_float($field)) {
                    return (string) $field;
                }

                return null;
            })
            ->filter(static fn (?string $field): bool => $field !== null)
            ->map(fn (string $field) => new Sort($field))
            ->unique(fn (Sort $sort) => $sort->getField())
            ->values();
    }

    /**
     * Parse fields parameter into grouped format.
     *
     * Supports three formats:
     * 1. String: 'resource.field1,resource.field2,simpleField'
     * 2. Sequential array: ['field1', 'resource.field2'] (treated as dot notation list)
     * 3. Associative array: ['resource' => ['field1', 'field2']]
     *
     * @return Collection<string, array<string>>
     */
    public function parseFields(mixed $value): Collection
    {
        if (is_string($value)) {
            $value = $this->parseFieldsString($value);
        } elseif (is_array($value) && $this->isSequentialArray($value)) {
            $value = $this->parseFieldsString(implode($this->arraySeparator, $value));
        }

        /** @var Collection<string, array<string>> */
        return collect($value)
            ->map(function ($fields) {
                if (is_string($fields)) {
                    $fields = $this->splitString($fields);
                }

                return $this->parseList($fields)->toArray();
            })
            ->filter();
    }

    /**
     * Check if array is sequential (numeric keys starting from 0).
     *
     * @param  array<mixed>  $array
     */
    private function isSequentialArray(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Parse fields string with dot notation into grouped format.
     *
     * Example: 'user.id,user.name,post.title,simpleField'
     * Returns: ['user' => ['id', 'name'], 'post' => ['title'], '' => ['simpleField']]
     *
     * @return array<string, array<string>>
     */
    private function parseFieldsString(string $fieldsString): array
    {
        $fields = $this->splitString($fieldsString);
        $grouped = [];

        foreach ($fields as $field) {
            $field = trim($field);
            if ($field === '') {
                continue;
            }

            $lastDotPos = strrpos($field, '.');
            if ($lastDotPos !== false) {
                $resource = substr($field, 0, $lastDotPos);
                $fieldName = substr($field, $lastDotPos + 1);
            } else {
                $resource = '';
                $fieldName = $field;
            }

            if (! isset($grouped[$resource])) {
                $grouped[$resource] = [];
            }
            $grouped[$resource][] = $fieldName;
        }

        return $grouped;
    }

    /**
     * Split a string by separator.
     *
     * @return array<int, string>
     */
    private function splitString(string $value): array
    {
        if ($this->arraySeparator === '') {
            return [$value];
        }

        return explode($this->arraySeparator, $value);
    }
}
