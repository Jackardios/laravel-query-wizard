<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

class MaxFieldsCountExceeded extends QueryLimitExceeded
{
    /**
     * How many distinct fields were counted before the check stopped: one more
     * than the limit when the request is read, not the total it names.
     */
    public readonly int $count;

    public readonly int $maxCount;

    public function __construct(int $count, int $maxCount)
    {
        $this->count = $count;
        $this->maxCount = $maxCount;

        $message = "The number of requested fields exceeds the maximum allowed ({$maxCount}).";
        parent::__construct($message, 'max_fields_count_exceeded', self::parameterName('fields'));
    }
}
