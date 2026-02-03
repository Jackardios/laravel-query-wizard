<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

class MaxIncludeDepthExceeded extends QueryLimitExceeded
{
    public int $depth;
    public int $maxDepth;
    public string $include;

    public function __construct(string $include, int $depth, int $maxDepth)
    {
        $this->include = $include;
        $this->depth = $depth;
        $this->maxDepth = $maxDepth;

        $message = "Include `{$include}` has depth {$depth} which exceeds the maximum allowed depth of {$maxDepth}.";
        parent::__construct($message);
    }

    public static function create(string $include, int $depth, int $maxDepth): self
    {
        return new self($include, $depth, $maxDepth);
    }
}
