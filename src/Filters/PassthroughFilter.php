<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Filters;

/**
 * Filter that passes validation but doesn't modify the query.
 *
 * Useful for capturing filter values without affecting the query,
 * e.g., for external API calls or custom processing.
 */
final class PassthroughFilter extends AbstractFilter
{
    /**
     * Create a new passthrough filter.
     *
     * @param string $name The filter name
     * @param string|null $alias Optional alias for URL parameter name
     */
    public static function make(string $name, ?string $alias = null): static
    {
        return new static($name, $alias);
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
