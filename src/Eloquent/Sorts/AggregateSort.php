<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Enums\SortDirection;
use Jackardios\QueryWizard\Sorts\AbstractSort;
use Jackardios\QueryWizard\Support\EloquentSubject;

/**
 * Sort by an aggregate of a related model's column.
 *
 * Uses `withAggregate` to add the aggregate column, named as Laravel names it
 * (`orders_sum_total`), then sorts by it.
 *
 * Example:
 *   EloquentSort::max('posts', 'created_at')  // Sort by newest post date
 *   EloquentSort::sum('orders', 'total')      // Sort by total order amount
 */
final class AggregateSort extends AbstractSort
{
    private const FUNCTIONS = ['min', 'max', 'sum', 'avg'];

    private string $column;

    /**
     * @var 'min'|'max'|'sum'|'avg'
     */
    private string $function;

    /**
     * @param  'min'|'max'|'sum'|'avg'  $function
     */
    protected function __construct(string $relation, string $column, string $function, ?string $alias = null)
    {
        parent::__construct($relation, $alias);
        $this->column = $column;
        $this->function = $function;
    }

    /**
     * Create a new aggregate sort.
     *
     * @param  string  $relation  The relationship name
     * @param  string  $column  The column on the related model
     * @param  string  $function  The aggregate function: min, max, sum or avg
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $relation, string $column, string $function, ?string $alias = null): static
    {
        if (! in_array($function, self::FUNCTIONS, true)) {
            throw new \InvalidArgumentException(
                "Invalid aggregate function `{$function}`. Allowed: ".implode(', ', self::FUNCTIONS).'. Use EloquentSort::count() to sort by a count.'
            );
        }

        if (str_contains($relation, '.')) {
            throw new \InvalidArgumentException(
                "An aggregate sort does not support nested relations (`{$relation}`). Use a callback sort instead."
            );
        }

        return new self($relation, $column, $function, $alias);
    }

    /**
     * Get the column being aggregated.
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * Get the aggregate function.
     *
     * @return 'min'|'max'|'sum'|'avg'
     */
    public function getFunction(): string
    {
        return $this->function;
    }

    /**
     * @param  Builder<Model>  $subject
     * @return Builder<Model>
     */
    public function apply(mixed $subject, SortDirection $direction): mixed
    {
        $aggregateColumn = EloquentSubject::aggregateAlias($this->property, $this->function, $this->column);

        if (! EloquentSubject::hasSelectAlias($subject, $aggregateColumn)) {
            $subject->withAggregate("{$this->property} as {$aggregateColumn}", $this->column, $this->function);
        }

        $subject->orderBy($aggregateColumn, $direction->value);

        return $subject;
    }
}
