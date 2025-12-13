<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\Concerns\ParsesRangeValues;

class DateRangeFilterStrategy implements FilterStrategyInterface
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
        $fromKey = $filter->getOption('fromKey', 'from');
        $toKey = $filter->getOption('toKey', 'to');
        $dateFormat = $filter->getOption('dateFormat');

        [$from, $to] = $this->parseRangeValue($value, $fromKey, $toKey);

        if ($from !== null) {
            $subject->where($column, '>=', $this->formatDate($from, $dateFormat));
        }

        if ($to !== null) {
            $subject->where($column, '<=', $this->formatDate($to, $dateFormat));
        }

        return $subject;
    }

    /**
     * @param DateTimeInterface|mixed $value
     */
    protected function formatDate(mixed $value, ?string $dateFormat): mixed
    {
        if ($value instanceof DateTimeInterface && $dateFormat !== null) {
            return $value->format($dateFormat);
        }

        return $value;
    }
}
