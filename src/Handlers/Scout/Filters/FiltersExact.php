<?php

namespace Jackardios\QueryWizard\Handlers\Scout\Filters;

use Jackardios\QueryWizard\Handlers\Scout\ScoutQueryHandler;
use Laravel\Scout\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class FiltersExact extends AbstractScoutFilter
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
            $this->addRelationConstraint($queryHandler, $value, $propertyName);

            return;
        }

        if (is_array($value)) {
            $query->whereIn($propertyName, $value);

            return;
        }

        $query->where($propertyName, $value);
    }

    protected function isRelationProperty(Builder $query, string $propertyName): bool
    {
        if (! Str::contains($propertyName, '.')) {
            return false;
        }

        $firstRelationship = explode('.', $propertyName)[0];

        if (! method_exists($query->model, $firstRelationship)) {
            return false;
        }

        return is_a($query->model->{$firstRelationship}(), Relation::class);
    }

    protected function addRelationConstraint(ScoutQueryHandler $queryHandler, $value, string $propertyName): void
    {
        $relation = Str::beforeLast($propertyName, '.');
        $propertyName = Str::afterLast($propertyName, '.');

        $queryHandler->addEloquentQueryCallback(function(EloquentBuilder $query) use ($relation, $propertyName, $value) {
            $query->whereHas($relation, function (EloquentBuilder $query) use ($propertyName, $value) {
                if (is_array($value)) {
                    $query->whereIn($query->qualifyColumn($propertyName), $value);

                    return;
                }

                $query->where($query->qualifyColumn($propertyName), '=', $value);
            });
        });
    }
}
