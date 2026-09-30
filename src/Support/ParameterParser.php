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
 *
 * @internal
 */
final class ParameterParser
{
    /**
     * @param  bool  $snakeCaseNames  Deduplicate and count items by their snake_case names, which they are converted to
     */
    public function __construct(
        private readonly string $arraySeparator = ',',
        private readonly bool $snakeCaseNames = false
    ) {}

    /**
     * Parse a list parameter (includes, appends, etc.) into a collection of distinct, trimmed, non-blank items.
     *
     * @return Collection<int, string>
     *
     * @throws \InvalidArgumentException When an array is keyed or holds a nested list
     * @throws ListLimitExceeded When the list names more than $maxCount distinct items
     */
    public function parseList(mixed $value, ?int $maxCount = null): Collection
    {
        $seen = [];

        return collect($this->distinctItems($this->items($value), $maxCount, $seen));
    }

    /**
     * Parse sorts parameter into Sort value objects, one per field.
     *
     * @return Collection<int, Sort>
     *
     * @throws \InvalidArgumentException When an array is keyed or holds a nested list
     * @throws ListLimitExceeded When the list sorts by more than $maxCount distinct fields
     */
    public function parseSorts(mixed $value, ?int $maxCount = null): Collection
    {
        $sorts = [];

        foreach ($this->items($value) as $field) {
            $field = self::listItem($field);

            if ($field === null || ltrim($field, '-') === '') {
                continue;
            }

            $sort = new Sort($field);
            $key = $this->pathKey($sort->getField());

            if (isset($sorts[$key])) {
                continue;
            }

            $sorts[$key] = $sort;

            if ($maxCount !== null && count($sorts) > $maxCount) {
                throw new ListLimitExceeded(count($sorts));
            }
        }

        return collect(array_values($sorts));
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
     *
     * @throws \InvalidArgumentException When a list is keyed or holds a nested list
     * @throws ListLimitExceeded When the groups name more than $maxCount distinct fields together
     */
    public function parseFields(mixed $value, ?int $maxCount = null): Collection
    {
        if (is_array($value) && array_is_list($value)) {
            $this->assertFlatList($value);
            $value = implode($this->arraySeparator, $value);
        }

        if (is_string($value)) {
            /** @var Collection<string, array<string>> */
            return collect(trim($value) === '' ? ['' => []] : $this->parseFieldsString($value, $maxCount));
        }

        if (! is_iterable($value)) {
            return collect();
        }

        $grouped = [];
        $seen = [];

        foreach ($value as $group => $fields) {
            $fields = is_string($fields) || is_iterable($fields) ? $this->items($fields) : [];

            $grouped[$group] = $this->distinctItems($fields, $maxCount, $seen, (string) $group);
        }

        /** @var Collection<string, array<string>> */
        return collect($grouped);
    }

    /**
     * @param  array<mixed>  $items
     *
     * @phpstan-assert array<int, scalar|null> $items
     *
     * @throws \InvalidArgumentException
     */
    private function assertFlatList(array $items): void
    {
        if (! array_is_list($items)) {
            throw new \InvalidArgumentException('Keyed lists are not supported.');
        }

        foreach ($items as $item) {
            if (is_array($item) || is_object($item)) {
                throw new \InvalidArgumentException('Nested lists are not supported.');
            }
        }
    }

    /**
     * Parse fields string with dot notation into grouped format.
     *
     * Example: 'user.id,user.name,post.title,simpleField'
     * Returns: ['user' => ['id', 'name'], 'post' => ['title'], '' => ['simpleField']]
     *
     * @return array<string, array<string>>
     *
     * @throws ListLimitExceeded
     */
    private function parseFieldsString(string $fieldsString, ?int $maxCount): array
    {
        $grouped = [];
        $seen = [];

        foreach ($this->split($fieldsString) as $field) {
            $field = trim($field);

            if ($field === '') {
                continue;
            }

            $lastDotPos = strrpos($field, '.');
            $resource = $lastDotPos === false ? '' : substr($field, 0, $lastDotPos);
            $fieldName = $lastDotPos === false ? $field : trim(substr($field, $lastDotPos + 1));
            $grouped[$resource] ??= [];
            $key = $this->fieldKey($resource, $fieldName);

            if ($fieldName === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $grouped[$resource][] = $fieldName;

            if ($maxCount !== null && count($seen) > $maxCount) {
                throw new ListLimitExceeded(count($seen));
            }
        }

        return $grouped;
    }

    /**
     * The distinct items of a list, trimmed, without blanks and values that are not scalars.
     *
     * @param  iterable<mixed>  $items
     * @param  array<array-key, true>  $seen  Keys of the items counted so far, shared by the lists of one parameter
     * @param  string|null  $group  The fieldset the items belong to, or null for a plain list
     * @return array<int, string>
     *
     * @throws ListLimitExceeded
     */
    private function distinctItems(iterable $items, ?int $maxCount, array &$seen, ?string $group = null): array
    {
        $distinct = [];

        foreach ($items as $item) {
            $item = self::listItem($item);

            if ($item === null) {
                continue;
            }

            $key = $group === null ? $this->pathKey($item) : $this->fieldKey($group, $item);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $distinct[] = $item;

            if ($maxCount !== null && count($seen) > $maxCount) {
                throw new ListLimitExceeded(count($seen));
            }
        }

        return $distinct;
    }

    private function pathKey(string $path): string
    {
        return $this->snakeCaseNames ? NameConverter::pathToSnakeCase($path) : $path;
    }

    private function fieldKey(string $group, string $field): string
    {
        return $this->snakeCaseNames
            ? NameConverter::pathToSnakeCase($group)."\0".NameConverter::toSnakeCase($field)
            : $group."\0".$field;
    }

    private static function listItem(mixed $item): ?string
    {
        if (is_string($item)) {
            $item = trim($item);

            return $item === '' ? null : $item;
        }

        return is_int($item) || is_float($item) ? (string) $item : null;
    }

    /**
     * @return iterable<mixed>
     */
    private function items(mixed $value): iterable
    {
        if (is_string($value)) {
            return $this->split($value);
        }

        if (is_array($value)) {
            $this->assertFlatList($value);

            return $value;
        }

        return is_iterable($value) ? self::flatItems($value) : [];
    }

    /**
     * The items of an iterable, checked one at a time so a reader that stops early never reads the rest.
     *
     * @param  iterable<mixed>  $items
     * @return \Generator<int, mixed>
     *
     * @throws \InvalidArgumentException When an item is a nested list
     */
    private static function flatItems(iterable $items): \Generator
    {
        foreach ($items as $item) {
            if (is_array($item) || is_object($item)) {
                throw new \InvalidArgumentException('Nested lists are not supported.');
            }

            yield $item;
        }
    }

    /**
     * Split a string by the separator one item at a time, so a reader that stops early never splits the rest.
     *
     * @return \Generator<int, string>
     */
    private function split(string $value): \Generator
    {
        if ($this->arraySeparator === '') {
            yield $value;

            return;
        }

        $offset = 0;
        $separatorLength = strlen($this->arraySeparator);

        while (($position = strpos($value, $this->arraySeparator, $offset)) !== false) {
            yield substr($value, $offset, $position - $offset);
            $offset = $position + $separatorLength;
        }

        yield substr($value, $offset);
    }
}
