<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

class ExactFilter extends AbstractEloquentFilter
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

    /** {@inheritdoc} */
    public function handle(AbstractQueryHandler $queryHandler, $queryBuilder, $value): void
    {
        $propertyName = $this->getPropertyName();

        if ($this->withRelationConstraint && $this->isRelationProperty($queryBuilder, $propertyName)) {
            $this->addRelationConstraint($queryBuilder, $value, $propertyName);

            return;
        }

        $this->applyOnQuery($queryBuilder, $value, $propertyName);
    }

    protected function applyOnQuery(Builder $queryBuilder, $value, string $propertyName): void
    {
        if (is_array($value)) {
            $queryBuilder->whereIn($queryBuilder->qualifyColumn($propertyName), $value);

            return;
        }

        $queryBuilder->where($queryBuilder->qualifyColumn($propertyName), '=', $value);
    }

    protected function isRelationProperty(Builder $queryBuilder, string $propertyName): bool
    {
        if (! Str::contains($propertyName, '.')) {
            return false;
        }

        $firstRelationship = explode('.', $propertyName)[0];

        if (! method_exists($queryBuilder->getModel(), $firstRelationship)) {
            return false;
        }

        return is_a($queryBuilder->getModel()->{$firstRelationship}(), Relation::class);
    }

    protected function addRelationConstraint(Builder $queryBuilder, $value, string $propertyName): void
    {
        $relation = Str::beforeLast($propertyName, '.');
        $propertyName = Str::afterLast($propertyName, '.');

        $queryBuilder->whereHas($relation, function (Builder $query) use ($value, $propertyName) {
            $this->applyOnQuery($query, $value, $propertyName);
        });
    }
}
