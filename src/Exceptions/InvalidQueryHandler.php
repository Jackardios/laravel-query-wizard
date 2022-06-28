<?php

namespace Jackardios\QueryWizard\Exceptions;

use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use LogicException;

class InvalidQueryHandler extends LogicException
{
    public function __construct(string $baseQueryHandlerClasses = AbstractQueryWizard::class)
    {
        parent::__construct("Invalid QueryHandler class. QueryHandler must extend `$baseQueryHandlerClasses`");
    }
}
