<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Values;

use InvalidArgumentException;
use Jackardios\QueryWizard\Enums\SortDirection;

/**
 * A requested or default sort: a field and its direction.
 */
final readonly class Sort
{
    private string $field;

    private SortDirection $direction;

    /**
     * @param  string  $field  Field name; a leading '-' means descending when no direction is given
     *
     * @throws InvalidArgumentException When the field has more than one leading '-', or one together with a direction
     */
    public function __construct(string $field, ?SortDirection $direction = null)
    {
        $descending = str_starts_with($field, '-');
        $name = $descending ? substr($field, 1) : $field;

        if (str_starts_with($name, '-')) {
            throw new InvalidArgumentException("Sort field `{$field}` has more than one leading `-`.");
        }

        if ($descending && $direction !== null) {
            throw new InvalidArgumentException("Sort field `{$field}` has a leading `-` and a direction; give one of them.");
        }

        $this->field = $name;
        $this->direction = $direction ?? ($descending ? SortDirection::Descending : SortDirection::Ascending);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getDirection(): SortDirection
    {
        return $this->direction;
    }

    public function isDescending(): bool
    {
        return $this->direction === SortDirection::Descending;
    }
}
