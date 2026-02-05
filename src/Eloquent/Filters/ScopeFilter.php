<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

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

/**
 * Filter by model scope.
 *
 * Calls the specified scope method on the model with the filter value(s).
 * Supports automatic model binding resolution for type-hinted parameters.
 *
 * SECURITY NOTE: When using resolveModelBindings (enabled by default),
 * ensure your scope methods include proper authorization checks.
 * The binding resolution will load any model by ID without authorization.
 *
 * Example safe usage:
 * ```php
 * public function scopeByAuthor(Builder $query, User $author)
 * {
 *     // Add authorization check
 *     if (!auth()->user()->can('view', $author)) {
 *         abort(403);
 *     }
 *     return $query->where('author_id', $author->id);
 * }
 * ```
 *
 * Or disable binding resolution:
 * ```php
 * EloquentFilter::scope('byAuthor')->resolveModelBindings(false)
 * ```
 */
final class ScopeFilter extends AbstractFilter
{
    protected bool $resolveModelBindings = true;

    /**
     * Cache for reflected scope method parameters.
     * Uses WeakMap to automatically clean up when model instances are garbage collected.
     *
     * @var WeakMap<Model, array<string, array<ReflectionParameter>|null>>|null
     */
    protected static ?WeakMap $reflectionCache = null;

    /**
     * Create a new scope filter.
     *
     * @param string $scope The scope method name (without 'scope' prefix)
     * @param string|null $alias Optional alias for URL parameter name
     */
    public static function make(string $scope, ?string $alias = null): static
    {
        return new static($scope, $alias);
    }

    /**
     * Enable or disable automatic model binding resolution.
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

        $scope = Str::camel((string) $propertyParts->pop());
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
