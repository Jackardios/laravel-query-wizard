<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

class MaxIncludesCountExceeded extends QueryLimitExceeded
{
    public readonly int $count;

    public readonly int $maxCount;

    public function __construct(int $count, int $maxCount)
    {
        $this->count = $count;
        $this->maxCount = $maxCount;

        $message = "The number of requested includes ({$count}) exceeds the maximum allowed ({$maxCount}).";
        parent::__construct($message, 'max_includes_count_exceeded', self::parameterName('includes'));
    }

    public static function create(int $count, int $maxCount): self
    {
        return new self($count, $maxCount);
    }
}
