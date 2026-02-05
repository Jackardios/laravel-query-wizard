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

/**
 * Filter by model scope.
 *
 * Calls the specified scope method on the model with the filter value(s).
 * Supports automatic model binding resolution for type-hinted parameters
 * (disabled by default for security).
 *
 * To enable model binding resolution:
 * ```php
 * EloquentFilter::scope('byAuthor')->withModelBinding()
 * ```
 *
 * SECURITY NOTE: When using withModelBinding(), ensure your scope
 * methods include proper authorization checks. The binding resolution
 * will load any model by ID without authorization.
 *
 * Example safe usage:
 * ```php
 * public function scopeByAuthor(Builder $query, User $author)
 * {
 *     abort_unless(auth()->user()->can('view', $author), 403);
 *     return $query->where('author_id', $author->id);
 * }
 * ```
 */
final class ScopeFilter extends AbstractFilter
{
    protected bool $resolveModelBindings = false;

    /**
     * Create a new scope filter.
     *
     * @param  string  $scope  The scope method name (without 'scope' prefix)
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $scope, ?string $alias = null): static
    {
        return new self($scope, $alias);
    }

    /**
     * Enable automatic model binding resolution for type-hinted parameters.
     *
     * When enabled, filter values are resolved to model instances using
     * Laravel's resolveRouteBinding() method.
     *
     * Note: This method mutates the current instance.
     */
    public function withModelBinding(): static
    {
        $this->resolveModelBindings = true;

        return $this;
    }

    /**
     * Disable automatic model binding resolution (default).
     *
     * Note: This method mutates the current instance.
     */
    public function withoutModelBinding(): static
    {
        $this->resolveModelBindings = false;

        return $this;
    }

    public function getType(): string
    {
        return 'scope';
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $subject
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     *
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
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $queryBuilder
     * @param  array<int, mixed>  $values
     * @return array<int, mixed>
     *
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
            if ($class === null || ! $class->isSubclassOf(Model::class)) {
                continue;
            }

            $index = $parameter->getPosition() - 1;
            $value = $values[$index] ?? null;

            if ($value === null) {
                continue;
            }

            $result = $class->newInstance()->resolveRouteBinding($value);

            if ($result === null) {
                throw InvalidFilterValue::make($value, $this->getName());
            }

            $values[$index] = $result;
        }

        return $values;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $queryBuilder
     * @return array<ReflectionParameter>|null
     */
    protected function getScopeParameters(Builder $queryBuilder, string $scope): ?array
    {
        $className = get_class($queryBuilder->getModel());
        $scopeKey = 'scope'.ucfirst($scope);

        try {
            return (new ReflectionClass($className))
                ->getMethod($scopeKey)
                ->getParameters();
        } catch (ReflectionException) {
            return null;
        }
    }

    /**
     * @return ReflectionClass<object>|null
     *
     * @throws ReflectionException
     */
    protected function getClass(ReflectionParameter $parameter): ?ReflectionClass
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType) {
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
