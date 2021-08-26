<?php

namespace Jackardios\QueryWizard\Exceptions;

use Jackardios\QueryWizard\Abstracts\Handlers\Includes\AbstractInclude;
use LogicException;

class InvalidIncludeHandler extends LogicException
{
    public function __construct(string $baseIncludeHandlerClass = AbstractInclude::class)
    {
        parent::__construct("Invalid IncludeHandler class. IncludeHandler must extend `$baseIncludeHandlerClass`");
    }
}
