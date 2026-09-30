<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

class MaxFiltersCountExceeded extends QueryLimitExceeded
{
    public const ERROR_CODE = 'max_filters_count_exceeded';

    /**
     * How many filters the request names. Like `$count` on the other limit
     * exceptions it is at least one more than the limit; here it is also the total.
     */
    public readonly int $count;

    public readonly int $maxCount;

    public function __construct(int $count, int $maxCount)
    {
        $this->count = $count;
        $this->maxCount = $maxCount;

        $message = "The number of requested filters ({$count}) exceeds the maximum allowed ({$maxCount}).";
        parent::__construct($message, self::ERROR_CODE, self::parameterName('filters'));
    }
}
