<?php

namespace Jackardios\QueryWizard\Exceptions;

use Jackardios\QueryWizard\Abstracts\Handlers\Filters\AbstractFilter;
use LogicException;

class InvalidFilterHandler extends LogicException
{
    /**
     * @param string[] $baseFilterHandlerClasses
     */
    public function __construct(array $baseFilterHandlerClasses = [AbstractFilter::class])
    {
        $baseFilterHandlerClassesImploded = implode('` or `', $baseFilterHandlerClasses);
        parent::__construct("Invalid FilterHandler class. FilterHandler must extend `$baseFilterHandlerClassesImploded`");
    }
}
