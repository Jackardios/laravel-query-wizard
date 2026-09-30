<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Jackardios\QueryWizard\Eloquent\Filters\Concerns\HandlesRelationFiltering;
use Jackardios\QueryWizard\Filters\AbstractFilter;
use Jackardios\QueryWizard\Support\FilterValueParser;

/**
 * Filter by NULL/NOT NULL values.
 *
 * Supports dot notation for relation filtering (e.g., 'posts.deleted_at').
 *
 * make() ("is null"):
 * - true → WHERE column IS NULL
 * - false → WHERE column IS NOT NULL
 *
 * notNull() ("is not null") reverses both. A value that is not a boolean
 * (true/false, 1/0, yes/no, on/off) is rejected with a 400.
 */
final class NullFilter extends AbstractFilter
{
    /** @use HandlesRelationFiltering<bool> */
    use HandlesRelationFiltering;

    private bool $matchesNotNull = false;

    /**
     * Create a filter where true matches NULL and false matches NOT NULL.
     *
     * @param  string  $property  The column name to check for NULL
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /**
     * Create a filter where true matches NOT NULL and false matches NULL.
     *
     * @param  string  $property  The column name to check for NULL
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function notNull(string $property, ?string $alias = null): static
    {
        $filter = new self($property, $alias);
        $filter->matchesNotNull = true;

        return $filter;
    }

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOnlyValueShape($value);
    }

    /**
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        return $this->applyToSubject($subject, $value);
    }

    protected function resolveConstraint(mixed $value): ?bool
    {
        return FilterValueParser::boolean($value, $this);
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  bool  $value  Whether the filter asks for null values
     * @return Builder<Model>
     */
    protected function applyOnQuery(Builder $builder, mixed $value, string $column): Builder
    {
        $qualifiedColumn = $builder->qualifyColumn($column);
        $shouldBeNull = $this->matchesNotNull ? ! $value : $value;

        if ($shouldBeNull) {
            $builder->whereNull($qualifiedColumn);
        } else {
            $builder->whereNotNull($qualifiedColumn);
        }

        return $builder;
    }
}
