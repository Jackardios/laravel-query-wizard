<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

class ScopeFilterStrategy implements FilterStrategyInterface
{
    /**
     * Cache for reflected scope method parameters
     * @var array<string, array<ReflectionParameter>|null>
     */
    protected static array $reflectionCache = [];

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     * @throws InvalidFilterValue
     * @throws ReflectionException
     */
    public function apply(mixed $subject, FilterDefinitionInterface $filter, mixed $value): mixed
    {
        $propertyName = $filter->getProperty();
        $propertyParts = collect(explode('.', $propertyName));

        $scope = Str::camel($propertyParts->pop());
        $values = array_values(Arr::wrap($value));
        $values = $this->resolveParameters($subject, $values, $scope);

        $relation = $propertyParts->implode('.');

        if ($relation) {
            $subject->whereHas($relation, function (Builder $query) use ($scope, $values): void {
                $query->$scope(...$values);
            });

            return $subject;
        }

        $subject->$scope(...$values);

        return $subject;
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $queryBuilder
     * @param array<int, mixed> $values
     * @return array<int, mixed>
     * @throws ReflectionException
     * @throws InvalidFilterValue
     */
    protected function resolveParameters(Builder $queryBuilder, array $values, string $scope): array
    {
        $parameters = $this->getScopeParameters($queryBuilder, $scope);

        if ($parameters === null) {
            return $values;
        }

        foreach ($parameters as $parameter) {
            $class = $this->getClass($parameter);
            if ($class === null || !$class->isSubclassOf(Model::class)) {
                continue;
            }

            $index = $parameter->getPosition() - 1;
            $value = $values[$index] ?? null;

            if ($value === null) {
                continue;
            }

            $result = $class->newInstance()->resolveRouteBinding($value);

            if ($result === null) {
                throw InvalidFilterValue::make($value);
            }

            $values[$index] = $result;
        }

        return $values;
    }

    /**
     * Get scope method parameters with caching
     *
     * @param Builder<\Illuminate\Database\Eloquent\Model> $queryBuilder
     * @return array<ReflectionParameter>|null
     */
    protected function getScopeParameters(Builder $queryBuilder, string $scope): ?array
    {
        $modelClass = get_class($queryBuilder->getModel());
        $cacheKey = $modelClass . '::scope' . ucfirst($scope);

        if (array_key_exists($cacheKey, self::$reflectionCache)) {
            return self::$reflectionCache[$cacheKey];
        }

        try {
            $parameters = (new \ReflectionObject($queryBuilder->getModel()))
                ->getMethod('scope' . ucfirst($scope))
                ->getParameters();
            return self::$reflectionCache[$cacheKey] = $parameters;
        } catch (ReflectionException) {
            return self::$reflectionCache[$cacheKey] = null;
        }
    }

    /**
     * @return ReflectionClass<object>|null
     * @throws ReflectionException
     */
    protected function getClass(ReflectionParameter $parameter): ?ReflectionClass
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType) {
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
