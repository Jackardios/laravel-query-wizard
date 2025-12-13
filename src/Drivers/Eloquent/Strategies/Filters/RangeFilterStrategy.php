<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\Concerns\ParsesRangeValues;

class RangeFilterStrategy implements FilterStrategyInterface
{
    use ParsesRangeValues;

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $subject
     * @param array<string, mixed>|mixed $value
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, FilterDefinitionInterface $filter, mixed $value): mixed
    {
        $column = $subject->qualifyColumn($filter->getProperty());
        $minKey = $filter->getOption('minKey', 'min');
        $maxKey = $filter->getOption('maxKey', 'max');

        [$min, $max] = $this->parseRangeValue($value, $minKey, $maxKey);

        if ($min !== null) {
            $subject->where($column, '>=', $min);
        }

        if ($max !== null) {
            $subject->where($column, '<=', $max);
        }

        return $subject;
    }
}
