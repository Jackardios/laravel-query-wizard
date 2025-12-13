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
use WeakMap;

class ScopeFilterStrategy implements FilterStrategyInterface
{
    /**
     * Cache for reflected scope method parameters.
     * Uses WeakMap to automatically clean up when model instances are garbage collected,
     * making it safe for Laravel Octane and other long-running processes.
     *
     * @var WeakMap<Model, array<string, array<ReflectionParameter>|null>>|null
     */
    protected static ?WeakMap $reflectionCache = null;

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
     * Get the reflection cache WeakMap instance
     *
     * @return WeakMap<Model, array<string, array<ReflectionParameter>|null>>
     */
    protected static function getReflectionCache(): WeakMap
    {
        if (self::$reflectionCache === null) {
            /** @var WeakMap<Model, array<string, array<ReflectionParameter>|null>> $cache */
            $cache = new WeakMap();
            self::$reflectionCache = $cache;
        }

        return self::$reflectionCache;
    }

    /**
     * Get scope method parameters with caching
     *
     * @param Builder<\Illuminate\Database\Eloquent\Model> $queryBuilder
     * @return array<ReflectionParameter>|null
     */
    protected function getScopeParameters(Builder $queryBuilder, string $scope): ?array
    {
        $model = $queryBuilder->getModel();
        $cache = self::getReflectionCache();
        $scopeKey = 'scope' . ucfirst($scope);

        // Initialize cache entry for this model if not exists
        if (!isset($cache[$model])) {
            $cache[$model] = [];
        }

        /** @var array<string, array<ReflectionParameter>|null> $modelCache */
        $modelCache = $cache[$model];

        if (array_key_exists($scopeKey, $modelCache)) {
            return $modelCache[$scopeKey];
        }

        try {
            $parameters = (new \ReflectionObject($model))
                ->getMethod($scopeKey)
                ->getParameters();
            $modelCache[$scopeKey] = $parameters;
            $cache[$model] = $modelCache;
            return $parameters;
        } catch (ReflectionException) {
            $modelCache[$scopeKey] = null;
            $cache[$model] = $modelCache;
            return null;
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
