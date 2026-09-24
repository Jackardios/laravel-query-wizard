<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Includes;

use Closure;
use Jackardios\QueryWizard\Contracts\ProvidesRuntimeAttributes;

/**
 * Include using a custom callback function.
 *
 * The callback receives ($subject, $relation) parameters.
 * This is a generic implementation that can be used across different query builders.
 *
 * @phpstan-consistent-constructor
 */
class CallbackInclude extends AbstractInclude implements ProvidesRuntimeAttributes
{
    /** @var Closure(mixed, string): mixed */
    protected Closure $callback;

    /** @var list<string> */
    protected array $runtimeAttributes = [];

    /**
     * @param  Closure(mixed, string): mixed  $callback
     */
    protected function __construct(
        string $relation,
        Closure $callback,
        ?string $alias = null,
    ) {
        parent::__construct($relation, $alias);
        $this->callback = $callback;
    }

    /**
     * Create a new callback include.
     *
     * @param  string  $relation  The relation/include name
     * @param  callable(mixed $subject, string $relation): mixed  $callback
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

    /**
     * Declare attributes the callback adds to the models, such as a
     * `withCount()` alias, so sparse fieldsets keep them visible.
     */
    public function withRuntimeAttributes(string ...$attributes): static
    {
        $this->runtimeAttributes = array_values($attributes);

        return $this;
    }

    public function runtimeAttributes(): array
    {
        return $this->runtimeAttributes;
    }

    public function apply(mixed $subject): mixed
    {
        return ($this->callback)($subject, $this->relation) ?? $subject;
    }
}
