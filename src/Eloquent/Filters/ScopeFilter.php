<?php

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use ReflectionParameter;
use ReflectionUnionType;

class ScopeFilter extends EloquentFilter
{
    /**
     * @throws InvalidFilterValue
     * @throws ReflectionException
     */
    public function handle($queryWizard, Builder $queryBuilder, $value): void
    {
        $propertyName = $this->getPropertyName();
        $propertyParts = collect(explode('.', $propertyName));

        $scope = Str::camel($propertyParts->pop());
        $values = array_values(Arr::wrap($value));
        $values = $this->resolveParameters($queryBuilder, $values, $scope);

        $relation = $propertyParts->implode('.');

        if ($relation) {
            $queryBuilder->whereHas($relation, function (Builder $query) use (
                $scope,
                $values
            ) {
                return $query->$scope(...$values);
            });

            return;
        }

        $queryBuilder->$scope(...$values);
    }

    /**
     * @throws ReflectionException
     * @throws InvalidFilterValue
     */
    protected function resolveParameters(Builder $queryBuilder, $values, string $scope): array
    {
        try {
            $parameters = (new ReflectionObject($queryBuilder->getModel()))
                ->getMethod('scope' . ucfirst($scope))
                ->getParameters();
        } catch (ReflectionException $e) {
            return $values;
        }

        foreach ($parameters as $parameter) {
            if (! optional($this->getClass($parameter))->isSubclassOf(Model::class)) {
                continue;
            }

            $index = $parameter->getPosition() - 1;
            $value = $values[$index];

            $class = $this->getClass($parameter);
            $result = $class?->newInstance()->resolveRouteBinding($value);

            if ($result === null) {
                throw InvalidFilterValue::make($value);
            }

            $values[$index] = $result;
        }

        return $values;
    }

    /**
     * @throws ReflectionException
     */
    protected function getClass(ReflectionParameter $parameter): ?ReflectionClass
    {
        if (PHP_VERSION_ID < 80000) {
            return $parameter->getClass();
        }

        $type = $parameter->getType();

        if (is_null($type)) {
            return null;
        }

        if ($type instanceof ReflectionUnionType) {
            return null;
        }

        if ($type->isBuiltin()) {
            return null;
        }

        if ($type->getName() === 'self') {
            return $parameter->getDeclaringClass();
        }

        return new ReflectionClass($type->getName());
    }
}
