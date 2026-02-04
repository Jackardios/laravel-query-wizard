<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Filters;

use Closure;

/**
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
     * @param callable(mixed $subject, mixed $value, string $property): void $callback
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
