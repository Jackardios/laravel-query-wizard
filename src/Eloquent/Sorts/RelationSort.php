<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Sorts\AbstractSort;

/**
 * Sort by a related model's field using aggregate functions.
 *
 * Uses `withAggregate` to add an aggregate column, then sorts by it.
 *
 * Example:
 *   EloquentSort::relation('posts', 'created_at', 'max')  // Sort by newest post date
 *   EloquentSort::relation('orders', 'total', 'sum')      // Sort by total order amount
 */
final class RelationSort extends AbstractSort
{
    private const ALLOWED_AGGREGATES = ['min', 'max', 'sum', 'avg', 'count', 'exists'];

    protected string $column;

    protected string $aggregate;

    /**
     * Create a new relation sort.
     *
     * @param  string  $relation  The relationship name
     * @param  string  $column  The column on the related model
     * @param  string  $aggregate  The aggregate function (max, min, sum, avg, count, exists)
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(
        string $relation,
        string $column,
        string $aggregate = 'max',
        ?string $alias = null
    ): static {
        if (! in_array($aggregate, self::ALLOWED_AGGREGATES, true)) {
            throw new \InvalidArgumentException(
                "Invalid aggregate `{$aggregate}`. Allowed: ".implode(', ', self::ALLOWED_AGGREGATES).'.'
            );
        }

        $instance = new self($relation, $alias);
        $instance->column = $column;
        $instance->aggregate = $aggregate;

        return $instance;
    }

    public function getType(): string
    {
        return 'relation';
    }

    /**
     * Get the column name being aggregated.
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * Get the aggregate function.
     */
    public function getAggregate(): string
    {
        return $this->aggregate;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $subject
     * @param  'asc'|'desc'  $direction
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, string $direction): mixed
    {
        // Laravel uses snake_case for aggregate columns: {relation}_{aggregate}_{column}
        $aggregateColumn = Str::snake($this->property).'_'.$this->aggregate.'_'.$this->column;

        $subject->withAggregate($this->property, $this->column, $this->aggregate);
        $subject->orderBy($aggregateColumn, $direction);

        return $subject;
    }
}
