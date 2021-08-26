<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FiltersExact extends AbstractEloquentFilter
{
    protected array $relationConstraints = [];
    protected bool $withRelationConstraint = true;

    public function __construct(
        string $name,
        ?string $propertyName = null,
        $default = null,
        $withRelationConstraint = true
    )
    {
        parent::__construct($name, $propertyName, $default);
        $this->withRelationConstraint = $withRelationConstraint;
    }

    public function withRelationConstraint(bool $value = true): void
    {
        $this->withRelationConstraint = $value;
    }

    public function handle($queryHandler, $query, $value): void
    {
        $propertyName = $this->getPropertyName();

        $this->handleForQuery($query, $value, $propertyName);
    }

    protected function handleForQuery($query, $value, string $propertyName): void
    {
        if ($this->withRelationConstraint && $this->isRelationProperty($query, $propertyName)) {
            $this->addRelationConstraint($query, $value, $propertyName);

            return;
        }

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

        if (in_array($propertyName, $this->relationConstraints, true)) {
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
        [$relation, $propertyName] = collect(explode('.', $propertyName))
            ->pipe(function (Collection $parts) {
                return [
                    $parts->except(count($parts) - 1)->implode('.'),
                    $parts->last(),
                ];
            });

        $query->whereHas($relation, function (Builder $query) use ($value, $propertyName) {
            $this->relationConstraints[] = $propertyName = $query->qualifyColumn($propertyName);

            $this->handleForQuery($query, $value, $propertyName);
        });
    }
}
