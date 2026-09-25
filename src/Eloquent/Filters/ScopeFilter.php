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
use Jackardios\QueryWizard\Support\FilterValueParser;
use Jackardios\QueryWizard\Support\RelationResolver;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Stringable;

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

    /** @var array<string, array{parameters: list<array{name: string, types: ?list<string>, nullable: bool, model: ?class-string<Model>}>, required: int, max: ?int}|null> */
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
     * values must fit the parameters, and each value must fit its
     * parameter's type: a bool parameter reads the value as a boolean.
     *
     * @param  array<int, mixed>  $values
     * @param  array{parameters: list<array{name: string, types: ?list<string>, nullable: bool, model: ?class-string<Model>}>, required: int, max: ?int}  $signature
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
     * @param  array{name: string, types: ?list<string>, nullable: bool, model: ?class-string<Model>}  $parameter
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

        if ($parameter['types'] === null) {
            return $argument;
        }

        foreach ($parameter['types'] as $type) {
            [$accepted, $converted] = self::acceptArgument($argument, $type);

            if ($accepted) {
                return $converted;
            }
        }

        $expected = implode(' or ', array_map(self::describeType(...), $parameter['types']));

        throw InvalidFilterValue::make($argument, $this, "Expected {$expected} for `{$parameter['name']}`.");
    }

    /**
     * @return array{bool, mixed}
     */
    private static function acceptArgument(mixed $argument, string $type): array
    {
        return match ($type) {
            'mixed' => [true, $argument],
            'int' => [self::isInteger($argument), $argument],
            'float' => [self::isNumber($argument), $argument],
            'string' => [is_scalar($argument) || $argument instanceof Stringable, $argument],
            'bool', 'true', 'false' => self::acceptBoolean($argument, $type),
            'array', 'iterable' => [is_array($argument), $argument],
            'object' => [is_object($argument), $argument],
            'callable' => [$argument instanceof \Closure, $argument],
            default => [self::isInstanceOfAll($argument, explode('&', $type)), $argument],
        };
    }

    /**
     * @param  list<string>  $classes
     */
    private static function isInstanceOfAll(mixed $argument, array $classes): bool
    {
        foreach ($classes as $class) {
            if (! $argument instanceof $class) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{bool, ?bool}
     */
    private static function acceptBoolean(mixed $argument, string $type): array
    {
        try {
            $boolean = FilterValueParser::isBlank($argument) ? null : FilterValueParser::boolean($argument, '');
        } catch (InvalidFilterValue) {
            $boolean = null;
        }

        $accepted = $boolean !== null && ($type === 'bool' || $boolean === ($type === 'true'));

        return [$accepted, $boolean];
    }

    private static function describeType(string $type): string
    {
        return match ($type) {
            'int' => 'an integer',
            'float' => 'a number',
            'string' => 'text',
            'bool' => 'a boolean',
            'true', 'false' => $type,
            'array', 'iterable' => 'a list',
            'object' => 'an object',
            'callable' => 'a callable',
            default => 'a '.implode('&', array_map(class_basename(...), explode('&', $type))),
        };
    }

    private static function isInteger(mixed $value): bool
    {
        return match (true) {
            is_int($value), is_bool($value) => true,
            is_float($value) => is_finite($value) && floor($value) === $value,
            is_string($value) => filter_var((string) preg_replace('/^([+-]?)0+(?=\d)/', '$1', trim($value)), FILTER_VALIDATE_INT) !== false,
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
     * @return array{parameters: list<array{name: string, types: ?list<string>, nullable: bool, model: ?class-string<Model>}>, required: int, max: ?int}|null
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
            $types = self::parameterTypes($parameter);
            $modelClass = $types !== null && count($types) === 1 && is_subclass_of($types[0], Model::class) ? $types[0] : null;

            $parameters[] = [
                'name' => $parameter->getName(),
                'types' => $types,
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

    /**
     * The type names a parameter accepts, with self and static resolved and
     * booleans last, so that a union reads a value as a boolean only when no
     * other type takes it; null when the parameter has no type. An
     * intersection is one entry joined with &.
     *
     * @return list<string>|null
     */
    private static function parameterTypes(ReflectionParameter $parameter): ?array
    {
        $type = $parameter->getType();
        $members = match (true) {
            $type instanceof ReflectionUnionType => $type->getTypes(),
            $type !== null => [$type],
            default => null,
        };

        if ($members === null) {
            return null;
        }

        $types = [];

        foreach ($members as $member) {
            $names = array_map(
                static fn (ReflectionType $part): string => $part instanceof ReflectionNamedType ? $part->getName() : 'mixed',
                $member instanceof ReflectionIntersectionType ? $member->getTypes() : [$member]
            );

            if ($names === ['null']) {
                continue;
            }

            $types[] = implode('&', array_map(
                static fn (string $name): string => in_array($name, ['self', 'static'], true)
                    ? ($parameter->getDeclaringClass()?->getName() ?? $name)
                    : $name,
                $names
            ));
        }

        $isBoolean = static fn (string $type): bool => in_array($type, ['bool', 'true', 'false'], true);

        return [...array_filter($types, static fn (string $type): bool => ! $isBoolean($type)), ...array_filter($types, $isBoolean)];
    }
}
