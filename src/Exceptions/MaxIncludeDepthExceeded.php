<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

class MaxIncludeDepthExceeded extends QueryLimitExceeded
{
    public readonly int $depth;

    public readonly int $maxDepth;

    public readonly string $include;

    public function __construct(string $include, int $depth, int $maxDepth)
    {
        $this->include = $include;
        $this->depth = $depth;
        $this->maxDepth = $maxDepth;

        $message = "Include `{$include}` has depth {$depth} which exceeds the maximum allowed depth of {$maxDepth}.";
        parent::__construct($message, 'max_include_depth_exceeded', self::parameterName('includes'));
    }
}
