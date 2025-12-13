<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Jackardios\QueryWizard\Contracts\IncludeStrategyInterface;
use LogicException;

class InvalidIncludeHandler extends LogicException
{
    /**
     * @param string[] $baseIncludeHandlerClasses
     */
    public function __construct(array $baseIncludeHandlerClasses = [IncludeStrategyInterface::class])
    {
        $baseIncludeHandlerClassesImploded = implode('` or `', $baseIncludeHandlerClasses);
        parent::__construct("Invalid IncludeHandler class. IncludeHandler must implement `$baseIncludeHandlerClassesImploded`");
    }
}
