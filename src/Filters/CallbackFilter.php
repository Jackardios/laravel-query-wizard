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
    /** @var Closure(mixed, mixed, string): mixed */
    protected Closure $callback;

    /**
     * @param  Closure(mixed, mixed, string): mixed  $callback
     */
    protected function __construct(
        string $property,
        Closure $callback,
        ?string $alias = null,
    ) {
        parent::__construct($property, $alias);
        $this->callback = $callback;
    }

    /**
     * Create a new callback filter.
     *
     * @param  string  $property  The filter property name
     * @param  callable(mixed $subject, mixed $value, string $property): mixed  $callback
     * @param  string|null  $alias  Optional alias for URL parameter name
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
        return ($this->callback)($subject, $value, $this->property) ?? $subject;
    }
}
