<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

class MaxFiltersCountExceeded extends QueryLimitExceeded
{
    public readonly int $count;

    public readonly int $maxCount;

    public function __construct(int $count, int $maxCount)
    {
        $this->count = $count;
        $this->maxCount = $maxCount;

        $message = "The number of requested filters ({$count}) exceeds the maximum allowed ({$maxCount}).";
        parent::__construct($message, 'max_filters_count_exceeded', self::parameterName('filters'));
    }
}
