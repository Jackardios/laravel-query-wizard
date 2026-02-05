<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Includes;

use Closure;

/**
 * Include using a custom callback function.
 *
 * The callback receives ($subject, $relation) parameters.
 * This is a generic implementation that can be used across different query builders.
 *
 * @phpstan-consistent-constructor
 */
class CallbackInclude extends AbstractInclude
{
    protected function __construct(
        string $relation,
        protected Closure $callback,
        ?string $alias = null,
    ) {
        parent::__construct($relation, $alias);
    }

    /**
     * Create a new callback include.
     *
     * @param  string  $relation  The relation/include name
     * @param  callable(mixed $subject, string $relation): void  $callback
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $relation, callable $callback, ?string $alias = null): static
    {
        return new static($relation, $callback(...), $alias);
    }

    public function getType(): string
    {
        return 'callback';
    }

    public function apply(mixed $subject): mixed
    {
        ($this->callback)($subject, $this->relation);

        return $subject;
    }
}
