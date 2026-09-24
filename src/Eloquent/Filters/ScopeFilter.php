<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Filters\AbstractFilter;
use Jackardios\QueryWizard\Support\EloquentSubject;
use Jackardios\QueryWizard\Support\RelationResolver;
use ReflectionMethod;
use ReflectionNamedType;

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

    /** @var array<string, array{parameters: list<array{name: string, type: ?string, nullable: bool, model: ?class-string<Model>}>, required: int, max: ?int}|null> */
    private static array $scopeSignatures = [];

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

    public function validatePreparedValueShape(mixed $value): ?string
    {
        return $this->validateNonArrayOrFlatListOfNonArraysValueShape($value);
    }

    /**
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     * @return Builder<Model>|Relation<Model, Model, mixed>
     *
     * @throws InvalidFilterValue
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        $segments = explode('.', $this->property);
        $scope = Str::camel(array_pop($segments));
        $relation = implode('.', $segments);
        $values = array_values(Arr::wrap($value));

        $query = $relation === ''
            ? $subject
            : (new RelationResolver($subject->getModel()))->resolve($relation)?->getRelated()->newQuery();

        if ($query !== null && ! $this->isShadowedByBuilder($query, $scope)) {
            $signature = self::scopeSignature(EloquentSubject::builder($query)->getModel(), $scope);

            if ($signature !== null) {
                $values = $this->resolveArguments($values, $value, $signature);
            }
        }

        if ($relation !== '') {
            $subject->whereHas($relation, function (Builder $query) use ($scope, $values): void {
                $query->$scope(...$values);
            });

            return $subject;
        }

        $subject->$scope(...$values);

        return $subject;
    }

    /**
     * A builder method or macro of that name is called instead of the scope.
     *
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $query
     */
    private function isShadowedByBuilder(Builder|Relation $query, string $scope): bool
    {
        if ($query instanceof Relation && (method_exists($query, $scope) || $query::hasMacro($scope))) {
            return true;
        }

        $builder = EloquentSubject::builder($query);

        return method_exists($builder, $scope) || $builder->hasMacro($scope) || Builder::hasGlobalMacro($scope);
    }

    /**
     * Check the values against the scope's parameters and resolve model bindings.
     *
     * A scope without parameters takes any value. Otherwise the number of
     * values must fit the parameters, and a value for an int or float
     * parameter must be a number.
     *
     * @param  array<int, mixed>  $values
     * @param  array{parameters: list<array{name: string, type: ?string, nullable: bool, model: ?class-string<Model>}>, required: int, max: ?int}  $signature
     * @return array<int, mixed>
     *
     * @throws InvalidFilterValue
     */
    private function resolveArguments(array $values, mixed $value, array $signature): array
    {
        if ($signature['max'] === 0) {
            return $values;
        }

        $count = count($values);

        if ($count < $signature['required'] || ($signature['max'] !== null && $count > $signature['max'])) {
            throw InvalidFilterValue::make($value, $this, self::expectedCount($signature['required'], $signature['max']));
        }

        $lastParameter = $signature['parameters'][count($signature['parameters']) - 1];

        foreach ($values as $index => $argument) {
            $values[$index] = $this->resolveArgument($argument, $signature['parameters'][$index] ?? $lastParameter);
        }

        return $values;
    }

    /**
     * @param  array{name: string, type: ?string, nullable: bool, model: ?class-string<Model>}  $parameter
     *
     * @throws InvalidFilterValue
     */
    private function resolveArgument(mixed $argument, array $parameter): mixed
    {
        if ($argument === null) {
            if (! $parameter['nullable']) {
                throw InvalidFilterValue::make($argument, $this, "Expected a value for `{$parameter['name']}`.");
            }

            return null;
        }

        if ($parameter['model'] !== null && $this->resolveModelBindings) {
            $model = new $parameter['model'];
            $resolved = $model->resolveRouteBinding($argument);

            if ($resolved === null) {
                $shortName = class_basename($model);

                throw InvalidFilterValue::make($argument, $this, "Expected the key of an existing {$shortName}.");
            }

            return $resolved;
        }

        $expected = match ($parameter['type']) {
            'int' => self::isInteger($argument) ? null : 'an integer',
            'float' => self::isNumber($argument) ? null : 'a number',
            default => null,
        };

        if ($expected !== null) {
            throw InvalidFilterValue::make($argument, $this, "Expected {$expected} for `{$parameter['name']}`.");
        }

        return $argument;
    }

    private static function isInteger(mixed $value): bool
    {
        return match (true) {
            is_int($value), is_bool($value) => true,
            is_float($value) => is_finite($value) && floor($value) === $value,
            is_string($value) => filter_var(trim($value), FILTER_VALIDATE_INT) !== false,
            default => false,
        };
    }

    private static function isNumber(mixed $value): bool
    {
        return is_int($value) || is_float($value) || is_bool($value) || (is_string($value) && is_numeric($value));
    }

    private static function expectedCount(int $required, ?int $max): string
    {
        $noun = static fn (int $count): string => $count === 1 ? 'value' : 'values';

        return match (true) {
            $max === null => "Expected at least {$required} {$noun($required)}.",
            $required === $max => "Expected {$max} {$noun($max)}.",
            default => "Expected {$required} to {$max} values.",
        };
    }

    /**
     * The parameters of a local scope after the query, or null when the model
     * has no such scope.
     *
     * @return array{parameters: list<array{name: string, type: ?string, nullable: bool, model: ?class-string<Model>}>, required: int, max: ?int}|null
     */
    private static function scopeSignature(Model $model, string $scope): ?array
    {
        $key = $model::class.'::'.$scope;

        if (array_key_exists($key, self::$scopeSignatures)) {
            return self::$scopeSignatures[$key];
        }

        if (! $model->hasNamedScope($scope)) {
            return self::$scopeSignatures[$key] = null;
        }

        $method = method_exists($model, 'scope'.ucfirst($scope)) ? 'scope'.ucfirst($scope) : $scope;
        $reflectionParameters = array_slice((new ReflectionMethod($model, $method))->getParameters(), 1);

        $parameters = [];
        $required = 0;
        $variadic = false;

        foreach ($reflectionParameters as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;
            $modelClass = null;

            if ($typeName !== null && ! $type->isBuiltin()) {
                $class = in_array($typeName, ['self', 'static'], true)
                    ? $parameter->getDeclaringClass()?->getName()
                    : $typeName;
                $modelClass = $class !== null && is_subclass_of($class, Model::class) ? $class : null;
                $typeName = null;
            }

            $parameters[] = [
                'name' => $parameter->getName(),
                'type' => $typeName,
                'nullable' => $type === null || $type->allowsNull(),
                'model' => $modelClass,
            ];

            $required += $parameter->isOptional() ? 0 : 1;
            $variadic = $variadic || $parameter->isVariadic();
        }

        return self::$scopeSignatures[$key] = [
            'parameters' => $parameters,
            'required' => $required,
            'max' => $variadic ? null : count($parameters),
        ];
    }
}
