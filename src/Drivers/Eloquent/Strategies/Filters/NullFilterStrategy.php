<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;

class NullFilterStrategy implements FilterStrategyInterface
{
    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, FilterDefinitionInterface $filter, mixed $value): mixed
    {
        $column = $subject->qualifyColumn($filter->getProperty());
        $invertLogic = $filter->getOption('invertLogic', false);

        // Use FILTER_NULL_ON_FAILURE to properly handle invalid values
        // Without it, filter_var returns null for invalid inputs which would be treated as falsy
        $isTruthy = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        $shouldBeNull = $invertLogic ? !$isTruthy : $isTruthy;

        if ($shouldBeNull) {
            $subject->whereNull($column);
        } else {
            $subject->whereNotNull($column);
        }

        return $subject;
    }
}
