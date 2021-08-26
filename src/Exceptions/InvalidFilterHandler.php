<?php

namespace Jackardios\QueryWizard\Exceptions;

use Jackardios\QueryWizard\Abstracts\Handlers\Filters\AbstractFilter;
use LogicException;

class InvalidFilterHandler extends LogicException
{
    public function __construct(string $baseFilterHandlerClass = AbstractFilter::class)
    {
        parent::__construct("Invalid FilterHandler class. FilterHandler must extend `$baseFilterHandlerClass`");
    }
}
