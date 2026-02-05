<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Filters\AbstractFilter;

/**
 * Filter for soft-deleted models.
 *
 * Values: 'with' (include trashed), 'only' (only trashed), anything else (exclude trashed)
 */
final class TrashedFilter extends AbstractFilter
{
    /**
     * Create a new trashed filter.
     *
     * @param  string|null  $alias  Optional alias for URL parameter name (default uses 'trashed')
     */
    public static function make(?string $alias = null): static
    {
        return new self('trashed', $alias);
    }

    public function getType(): string
    {
        return 'trashed';
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $subject
     * @param  'with'|'only'|mixed  $value
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        if ($value === 'with') {
            /** @phpstan-ignore method.notFound */
            $subject->withTrashed();

            return $subject;
        }

        if ($value === 'only') {
            /** @phpstan-ignore method.notFound */
            $subject->onlyTrashed();

            return $subject;
        }

        /** @phpstan-ignore method.notFound */
        $subject->withoutTrashed();

        return $subject;
    }
}
