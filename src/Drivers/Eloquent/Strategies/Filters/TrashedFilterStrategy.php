<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;

class TrashedFilterStrategy implements FilterStrategyInterface
{
    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @param 'with'|'only'|mixed $value
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, FilterDefinitionInterface $filter, mixed $value): mixed
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
