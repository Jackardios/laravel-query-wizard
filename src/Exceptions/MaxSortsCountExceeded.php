<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

class MaxSortsCountExceeded extends QueryLimitExceeded
{
    public const ERROR_CODE = 'max_sorts_count_exceeded';

    /**
     * How many distinct sorts were counted before the check stopped: one more
     * than the limit when the request is read, not the total it names.
     */
    public readonly int $count;

    public readonly int $maxCount;

    public function __construct(int $count, int $maxCount)
    {
        $this->count = $count;
        $this->maxCount = $maxCount;

        $message = "The number of requested sorts exceeds the maximum allowed ({$maxCount}).";
        parent::__construct($message, self::ERROR_CODE, self::parameterName('sorts'));
    }
}
