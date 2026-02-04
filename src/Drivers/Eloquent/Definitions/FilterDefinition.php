<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Definitions;

use Closure;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;

final class FilterDefinition implements FilterDefinitionInterface
{
    /**
     * @param (Closure(mixed): mixed)|null $prepareValueCallback
     * @param (Closure(mixed $query, mixed $value, string $property): void)|null $callback
     * @param class-string|null $strategyClass
     * @param array<string, mixed> $options
     */
    private function __construct(
        private string $property,
        private string $type,
        private ?string $alias = null,
        private mixed $default = null,
        private ?Closure $prepareValueCallback = null,
        private ?Closure $callback = null,
        private ?string $strategyClass = null,
        private array $options = [],
    ) {}

    public static function exact(string $property, ?string $alias = null): self
    {
        return new self($property, 'exact', $alias);
    }

    public static function partial(string $property, ?string $alias = null): self
    {
        return new self($property, 'partial', $alias);
    }

    public static function scope(string $scope, ?string $alias = null): self
    {
        return new self($scope, 'scope', $alias);
    }

    public static function trashed(?string $alias = null): self
    {
        return new self('trashed', 'trashed', $alias);
    }

    /**
     * @param callable(mixed $query, mixed $value, string $property): void $callback
     */
    public static function callback(string $property, callable $callback, ?string $alias = null): self
    {
        return new self($property, 'callback', $alias, callback: $callback(...));
    }

    /**
     * @param class-string $strategyClass
     */
    public static function custom(string $property, string $strategyClass, ?string $alias = null): self
    {
        return new self($property, 'custom', $alias, strategyClass: $strategyClass);
    }

    public static function range(string $property, ?string $alias = null): self
    {
        return new self($property, 'range', $alias);
    }

    public static function dateRange(string $property, ?string $alias = null): self
    {
        return new self($property, 'dateRange', $alias);
    }

    public static function null(string $property, ?string $alias = null): self
    {
        return new self($property, 'null', $alias);
    }

    public static function jsonContains(string $property, ?string $alias = null): self
    {
        return new self($property, 'jsonContains', $alias);
    }

    /**
     * Create a passthrough filter that captures value without applying to query.
     *
     * Use this when you want Query Wizard to validate and capture a filter value,
     * but handle the filtering logic yourself.
     *
     * @param string $name The filter name as it appears in the request
     */
    public static function passthrough(string $name): self
    {
        return new self($name, 'passthrough', $name);
    }

    /**
     * @return static
     */
    public function default(mixed $value): self
    {
        $clone = clone $this;
        $clone->default = $value;
        return $clone;
    }

    /**
     * @param Closure(mixed): mixed $callback
     * @return static
     */
    public function prepareValueWith(Closure $callback): self
    {
        $clone = clone $this;
        $clone->prepareValueCallback = $callback;
        return $clone;
    }

    /**
     * @param array<string, mixed> $options
     * @return static
     */
    public function withOptions(array $options): self
    {
        $clone = clone $this;
        $clone->options = array_merge($clone->options, $options);
        return $clone;
    }

    /**
     * @return static
     */
    public function withRelationConstraint(bool $value = true): self
    {
        return $this->withOptions(['withRelationConstraint' => $value]);
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->alias ?? $this->property;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getCallback(): ?Closure
    {
        return $this->callback;
    }

    /**
     * @return class-string|null
     */
    public function getStrategyClass(): ?string
    {
        return $this->strategyClass;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function prepareValue(mixed $value): mixed
    {
        if ($this->prepareValueCallback === null) {
            return $value;
        }

        return ($this->prepareValueCallback)($value);
    }
}
