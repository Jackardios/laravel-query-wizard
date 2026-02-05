<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Sorts\AbstractSort;

/**
 * Sort by a database column field.
 */
final class FieldSort extends AbstractSort
{
    /**
     * Create a new field sort.
     *
     * @param string $property The column name to sort on
     * @param string|null $alias Optional alias for URL parameter name
     */
    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'field';
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @param 'asc'|'desc' $direction
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, string $direction): mixed
    {
        $column = $subject->qualifyColumn($this->property);
        $subject->orderBy($column, $direction);

        return $subject;
    }
}
