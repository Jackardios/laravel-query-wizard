<?php

namespace Jackardios\QueryWizard\Exceptions;

use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;
use LogicException;

class InvalidQueryHandler extends LogicException
{
    public function __construct(string $baseQueryHandlerClasses = AbstractQueryHandler::class)
    {
        parent::__construct("Invalid QueryHandler class. QueryHandler must extend `$baseQueryHandlerClasses`");
    }
}
