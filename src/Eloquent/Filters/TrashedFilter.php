<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Filters\AbstractFilter;
use Jackardios\QueryWizard\Support\FilterValueParser;

/**
 * Filter for soft-deleted models.
 *
 * Values: 'with'/'true' (include trashed), 'only' (only trashed), 'without'/'false' (exclude trashed)
 * Any other value is rejected with a 400.
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

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOnlyValueShape($value);
    }

    /**
     * @param  Builder<Model>  $subject
     * @param  'with'|'only'|'without'|mixed  $value
     * @return Builder<Model>
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        match (FilterValueParser::trashedMode($value, $this)) {
            'with' => $subject->withTrashed(), // @phpstan-ignore method.notFound
            'only' => $subject->onlyTrashed(), // @phpstan-ignore method.notFound
            'without' => $subject->withoutTrashed(), // @phpstan-ignore method.notFound
            null => null,
        };

        return $subject;
    }
}
