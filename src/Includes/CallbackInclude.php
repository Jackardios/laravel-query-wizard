<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Includes;

use Closure;

/**
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
     * @param callable(mixed $subject, string $relation, array<string> $fields): void $callback
     */
    public static function make(string $relation, callable $callback, ?string $alias = null): static
    {
        return new static($relation, $callback(...), $alias);
    }

    public function getType(): string
    {
        return 'callback';
    }

    public function apply(mixed $subject, array $fields = []): mixed
    {
        ($this->callback)($subject, $this->relation, $fields);
        return $subject;
    }
}
