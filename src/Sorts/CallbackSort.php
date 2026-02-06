<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Sorts;

use Closure;

/**
 * Sort using a custom callback function.
 *
 * The callback receives ($subject, $direction, $property) parameters.
 * This is a generic implementation that can be used across different query builders.
 *
 * @phpstan-consistent-constructor
 */
class CallbackSort extends AbstractSort
{
    /** @var Closure(mixed, string, string): mixed */
    protected Closure $callback;

    /**
     * Create a new callback sort.
     *
     * @param  string  $property  The sort property name
     * @param  callable(mixed $subject, string $direction, string $property): mixed  $callback
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $property, callable $callback, ?string $alias = null): static
    {
        $instance = new static($property, $alias);
        $instance->callback = $callback(...);

        return $instance;
    }

    public function getType(): string
    {
        return 'callback';
    }

    public function apply(mixed $subject, string $direction): mixed
    {
        return ($this->callback)($subject, $direction, $this->property) ?? $subject;
    }
}
