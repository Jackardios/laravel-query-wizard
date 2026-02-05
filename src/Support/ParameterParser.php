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

        /** @var Collection<int, string> */
        return collect($value)
            ->map(fn ($item) => is_string($item) ? trim($item) : $item)
            ->filter()
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

        return collect($value)
            ->filter()
            ->map(fn ($field) => new Sort(trim((string) $field)))
            ->unique(fn (Sort $sort) => $sort->getField())
            ->values();
    }

    /**
     * Parse fields parameter into grouped format.
     *
     * Supports two formats:
     * 1. Array: ['resource' => ['field1', 'field2']]
     * 2. String: 'resource.field1,resource.field2,simpleField'
     *
     * @return Collection<string, array<string>>
     */
    public function parseFields(mixed $value): Collection
    {
        if (is_string($value)) {
            $value = $this->parseFieldsString($value);
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
