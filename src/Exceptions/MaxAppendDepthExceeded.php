<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

class MaxAppendDepthExceeded extends QueryLimitExceeded
{
    public const ERROR_CODE = 'max_append_depth_exceeded';

    public readonly string $append;

    public readonly int $depth;

    public readonly int $maxDepth;

    public function __construct(string $append, int $depth, int $maxDepth)
    {
        $this->append = $append;
        $this->depth = $depth;
        $this->maxDepth = $maxDepth;

        $message = "Append `{$append}` has depth {$depth} which exceeds the maximum allowed depth of {$maxDepth}.";
        parent::__construct($message, self::ERROR_CODE, self::parameterName('appends'));
    }
}
