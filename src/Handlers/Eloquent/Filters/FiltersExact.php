<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class FiltersExact extends AbstractEloquentFilter
{
    protected bool $withRelationConstraint = true;

    public function __construct(
        string $propertyName,
        ?string $alias = null,
        $default = null,
        $withRelationConstraint = true
    )
    {
        parent::__construct($propertyName, $alias, $default);
        $this->withRelationConstraint = $withRelationConstraint;
    }

    public function withRelationConstraint(bool $value = true): void
    {
        $this->withRelationConstraint = $value;
    }

    public function handle($queryHandler, $query, $value): void
    {
        $propertyName = $this->getPropertyName();

        if ($this->withRelationConstraint && $this->isRelationProperty($query, $propertyName)) {
            $this->addRelationConstraint($query, $value, $propertyName);

            return;
        }

        $this->applyOnQuery($query, $value, $propertyName);
    }

    protected function applyOnQuery(Builder $query, $value, string $propertyName): void
    {
        if (is_array($value)) {
            $query->whereIn($query->qualifyColumn($propertyName), $value);

            return;
        }

        $query->where($query->qualifyColumn($propertyName), '=', $value);
    }

    protected function isRelationProperty(Builder $query, string $propertyName): bool
    {
        if (! Str::contains($propertyName, '.')) {
            return false;
        }

        $firstRelationship = explode('.', $propertyName)[0];

        if (! method_exists($query->getModel(), $firstRelationship)) {
            return false;
        }

        return is_a($query->getModel()->{$firstRelationship}(), Relation::class);
    }

    protected function addRelationConstraint(Builder $query, $value, string $propertyName): void
    {
        $relation = Str::beforeLast($propertyName, '.');
        $propertyName = Str::afterLast($propertyName, '.');

        $query->whereHas($relation, function (Builder $query) use ($value, $propertyName) {
            $this->applyOnQuery($query, $value, $propertyName);
        });
    }
}
