<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Filters;

use Closure;

/**
 * Filter using a custom callback function.
 *
 * The callback receives ($subject, $value, $property) parameters.
 * This is a generic implementation that can be used across different query builders.
 *
 * @phpstan-consistent-constructor
 */
class CallbackFilter extends AbstractFilter
{
    protected function __construct(
        string $property,
        protected Closure $callback,
        ?string $alias = null,
    ) {
        parent::__construct($property, $alias);
    }

    /**
     * Create a new callback filter.
     *
     * @param string $property The filter property name
     * @param callable(mixed $subject, mixed $value, string $property): void $callback
     * @param string|null $alias Optional alias for URL parameter name
     */
    public static function make(string $property, callable $callback, ?string $alias = null): static
    {
        return new static($property, $callback(...), $alias);
    }

    public function getType(): string
    {
        return 'callback';
    }

    public function apply(mixed $subject, mixed $value): mixed
    {
        ($this->callback)($subject, $value, $this->property);
        return $subject;
    }
}
