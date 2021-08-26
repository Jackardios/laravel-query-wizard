<?php

namespace Jackardios\QueryWizard\Exceptions;

use Jackardios\QueryWizard\Abstracts\Handlers\Sorts\AbstractSort;
use LogicException;

class InvalidSortHandler extends LogicException
{
    public function __construct(string $baseSortHandlerClass = AbstractSort::class)
    {
        parent::__construct("Invalid SortHandler class. SortHandler must extend `$baseSortHandlerClass`");
    }
}
