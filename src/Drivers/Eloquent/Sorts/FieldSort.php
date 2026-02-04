<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Sorts\AbstractSort;

class FieldSort extends AbstractSort
{
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
