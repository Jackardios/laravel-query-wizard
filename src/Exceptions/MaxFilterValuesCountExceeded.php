<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

class MaxFilterValuesCountExceeded extends QueryLimitExceeded
{
    public const ERROR_CODE = 'max_filter_values_count_exceeded';

    public readonly string $filterName;

    /**
     * How many values were counted before the check stopped: one more than
     * the limit, not the total the filter received.
     */
    public readonly int $count;

    public readonly int $maxCount;

    public function __construct(string $filterName, int $count, int $maxCount)
    {
        $this->filterName = $filterName;
        $this->count = $count;
        $this->maxCount = $maxCount;

        $message = "Filter `{$filterName}` has more values than the maximum allowed ({$maxCount}).";
        parent::__construct($message, self::ERROR_CODE, self::parameterName('filters'));
    }
}
