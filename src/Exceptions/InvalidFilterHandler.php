<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;
use LogicException;

class InvalidFilterHandler extends LogicException
{
    /**
     * @param string[] $baseFilterHandlerClasses
     */
    public function __construct(array $baseFilterHandlerClasses = [FilterStrategyInterface::class])
    {
        $baseFilterHandlerClassesImploded = implode('` or `', $baseFilterHandlerClasses);
        parent::__construct("Invalid FilterHandler class. FilterHandler must implement `$baseFilterHandlerClassesImploded`");
    }
}
