<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Filters;

class PassthroughFilter extends AbstractFilter
{
    public static function make(string $name): static
    {
        return new static($name);
    }

    public function getType(): string
    {
        return 'passthrough';
    }

    public function apply(mixed $subject, mixed $value): mixed
    {
        // Intentionally does nothing - just return subject unchanged
        return $subject;
    }
}
