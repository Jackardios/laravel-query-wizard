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
 * By default:
 * - Truthy value → WHERE column IS NULL
 * - Falsy value → WHERE column IS NOT NULL
 *
 * When invertLogic is true, the behavior is reversed. A value that is not a
 * boolean (true/false, 1/0, yes/no, on/off) is rejected with a 400.
 */
final class NullFilter extends AbstractFilter
{
    /** @use HandlesRelationFiltering<bool> */
    use HandlesRelationFiltering;

    protected bool $invertLogic = false;

    /**
     * Create a new null filter.
     *
     * @param  string  $property  The column name to check for NULL
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /**
     * Invert the filter logic.
     * When inverted: truthy → NOT NULL, falsy → NULL
     *
     * Note: This method mutates the current instance.
     */
    public function withInvertedLogic(): static
    {
        $this->invertLogic = true;

        return $this;
    }

    /**
     * Use normal filter logic (default).
     * Normal: truthy → NULL, falsy → NOT NULL
     *
     * Note: This method mutates the current instance.
     */
    public function withoutInvertedLogic(): static
    {
        $this->invertLogic = false;

        return $this;
    }

    public function getType(): string
    {
        return 'null';
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
        $shouldBeNull = $this->invertLogic ? ! $value : $value;

        if ($shouldBeNull) {
            $builder->whereNull($qualifiedColumn);
        } else {
            $builder->whereNotNull($qualifiedColumn);
        }

        return $builder;
    }
}
