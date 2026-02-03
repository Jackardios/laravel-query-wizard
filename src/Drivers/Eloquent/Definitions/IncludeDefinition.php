<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Definitions;

use Closure;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;

final class IncludeDefinition implements IncludeDefinitionInterface
{
    /**
     * @param (Closure(mixed $query, string $relation, array<string> $fields): void)|null $callback
     * @param class-string|null $strategyClass
     * @param array<string, mixed> $options
     */
    private function __construct(
        private string $relation,
        private string $type,
        private ?string $alias = null,
        private ?Closure $callback = null,
        private ?string $strategyClass = null,
        private array $options = [],
    ) {}

    public static function relationship(string $relation, ?string $alias = null): self
    {
        return new self($relation, 'relationship', $alias);
    }

    public static function count(string $relation, ?string $alias = null): self
    {
        return new self($relation, 'count', $alias);
    }

    /**
     * @param callable(mixed $query, string $relation, array<string> $fields): void $callback
     */
    public static function callback(string $name, callable $callback, ?string $alias = null): self
    {
        return new self($name, 'callback', $alias, callback: $callback(...));
    }

    /**
     * @param class-string $strategyClass
     */
    public static function custom(string $relation, string $strategyClass, ?string $alias = null): self
    {
        return new self($relation, 'custom', $alias, strategyClass: $strategyClass);
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

    public function getRelation(): string
    {
        return $this->relation;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->alias ?? $this->relation;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
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
}
