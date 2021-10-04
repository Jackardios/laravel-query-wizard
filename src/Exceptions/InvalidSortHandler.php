<?php

namespace Jackardios\QueryWizard\Exceptions;

use Jackardios\QueryWizard\Abstracts\Handlers\Sorts\AbstractSort;
use LogicException;

class InvalidSortHandler extends LogicException
{
    /**
     * @param string[] $baseSortHandlerClasses
     */
    public function __construct(array $baseSortHandlerClasses = [AbstractSort::class])
    {
        $baseSortHandlerClassesImploded = implode('` or `', $baseSortHandlerClasses);
        parent::__construct("Invalid SortHandler class. SortHandler must extend `$baseSortHandlerClassesImploded`");
    }
}
