<?php

namespace Jackardios\QueryWizard\Values;

use Jackardios\QueryWizard\Enums\SortDirection;

class Sort
{
    protected string $field;
    protected string $direction;

    public function __construct(string $field, ?string $direction = null)
    {
        $this->field = ltrim($field, '-');
        if ($direction) {
            $this->direction = $direction === SortDirection::DESCENDING ? SortDirection::DESCENDING : SortDirection::ASCENDING;
        } else {
            $this->direction = self::parseSortDirection($field);
        }
    }

    public function getField(): string {
        return $this->field;
    }

    public function getDirection(): string {
        return $this->direction;
    }

    public static function parseSortDirection(string $field): string
    {
        return strpos($field, '-') === 0 ? SortDirection::DESCENDING : SortDirection::ASCENDING;
    }
}
