<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Filters\AbstractFilter;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use WeakMap;

class ScopeFilter extends AbstractFilter
{
    protected bool $resolveModelBindings = true;

    /**
     * Cache for reflected scope method parameters.
     * Uses WeakMap to automatically clean up when model instances are garbage collected,
     * making it safe for Laravel Octane and other long-running processes.
     *
     * @var WeakMap<Model, array<string, array<ReflectionParameter>|null>>|null
     */
    protected static ?WeakMap $reflectionCache = null;

    public static function make(string $scope, ?string $alias = null): static
    {
        return new static($scope, $alias);
    }

    /**
     * Enable or disable automatic model binding resolution for scope parameters.
     *
     * When enabled (default), if a scope method has a parameter type-hinted to
     * an Eloquent model, the filter will automatically resolve the model from
     * the filter value using route model binding (resolveRouteBinding).
     *
     * This is useful when your scope expects a model instance but the request
     * provides an ID or other route key.
     *
     * Note: Filters are immutable. This method returns a new instance.
     *
     * @example Auto-resolve User model from ID
     * ```php
     * // Model scope: public function scopeByAuthor($query, User $user)
     * FilterDefinition::scope('byAuthor')
     * // ?filter[byAuthor]=5 → calls scopeByAuthor($query, User::find(5))
     * ```
     *
     * @example Disable model binding resolution
     * ```php
     * FilterDefinition::scope('byAuthor')->resolveModelBindings(false)
     * // ?filter[byAuthor]=5 → calls scopeByAuthor($query, '5')
     * ```
     *
     * @param bool $value Whether to resolve model bindings (default: true)
     * @return static New filter instance with the setting applied
     */
    public function resolveModelBindings(bool $value = true): static
    {
        $clone = clone $this;
        $clone->resolveModelBindings = $value;
        return $clone;
    }

    public function getType(): string
    {
        return 'scope';
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     * @throws InvalidFilterValue
     * @throws ReflectionException
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        $propertyParts = collect(explode('.', $this->property));

        $scope = Str::camel($propertyParts->pop());
        $values = array_values(Arr::wrap($value));

        if ($this->resolveModelBindings) {
            $values = $this->resolveParameters($subject, $values, $scope);
        }

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

        /** @var class-string $className */
        $className = $type->getName();
        return new ReflectionClass($className);
    }
}
