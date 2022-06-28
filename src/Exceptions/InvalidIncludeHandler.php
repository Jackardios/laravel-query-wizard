<?php

namespace Jackardios\QueryWizard\Exceptions;

use Jackardios\QueryWizard\Abstracts\AbstractInclude;
use LogicException;

class InvalidIncludeHandler extends LogicException
{
    /**
     * @param string[] $baseIncludeHandlerClasses
     */
    public function __construct(array $baseIncludeHandlerClasses = [AbstractInclude::class])
    {
        $baseIncludeHandlerClassesImploded = implode('` or `', $baseIncludeHandlerClasses);
        parent::__construct("Invalid IncludeHandler class. IncludeHandler must extend `$baseIncludeHandlerClassesImploded`");
    }
}
