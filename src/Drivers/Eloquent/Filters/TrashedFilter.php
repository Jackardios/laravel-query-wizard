<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Filters\AbstractFilter;

/**
 * @phpstan-consistent-constructor
 */
class TrashedFilter extends AbstractFilter
{
    protected function __construct(?string $alias = null)
    {
        parent::__construct('trashed', $alias);
    }

    public static function make(?string $alias = null): static
    {
        return new static($alias);
    }

    public function getType(): string
    {
        return 'trashed';
    }

    /**
     * Apply trashed filter. Expects model with SoftDeletes trait.
     *
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @param 'with'|'only'|mixed $value
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
