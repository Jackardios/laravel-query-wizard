<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Sorts;

use Closure;

/**
 * @phpstan-consistent-constructor
 */
class CallbackSort extends AbstractSort
{
    protected function __construct(
        string $property,
        protected Closure $callback,
        ?string $alias = null,
    ) {
        parent::__construct($property, $alias);
    }

    /**
     * @param callable(mixed $subject, string $direction, string $property): void $callback
     */
    public static function make(string $property, callable $callback, ?string $alias = null): static
    {
        return new static($property, $callback(...), $alias);
    }

    public function getType(): string
    {
        return 'callback';
    }

    public function apply(mixed $subject, string $direction): mixed
    {
        ($this->callback)($subject, $direction, $this->property);
        return $subject;
    }
}
