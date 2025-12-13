<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Jackardios\QueryWizard\Contracts\SortStrategyInterface;
use LogicException;

class InvalidSortHandler extends LogicException
{
    /**
     * @param string[] $baseSortHandlerClasses
     */
    public function __construct(array $baseSortHandlerClasses = [SortStrategyInterface::class])
    {
        $baseSortHandlerClassesImploded = implode('` or `', $baseSortHandlerClasses);
        parent::__construct("Invalid SortHandler class. SortHandler must implement `$baseSortHandlerClassesImploded`");
    }
}
