<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Values;

use Jackardios\QueryWizard\Enums\SortDirection;

class Sort
{
    protected string $field;
    protected SortDirection $direction;

    /**
     * @param string $field Field name (may include leading '-' for descending)
     */
    public function __construct(string $field, ?SortDirection $direction = null)
    {
        $this->field = ltrim($field, '-');
        $this->direction = $direction ?? self::parseSortDirection($field);
    }

    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @return 'asc'|'desc'
     */
    public function getDirection(): string
    {
        return $this->direction->value;
    }

    public function getSortDirection(): SortDirection
    {
        return $this->direction;
    }

    public static function parseSortDirection(string $field): SortDirection
    {
        return str_starts_with($field, '-') ? SortDirection::Descending : SortDirection::Ascending;
    }
}
