<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Jackardios\QueryWizard\QueryWizard;
use LogicException;

class InvalidQueryHandler extends LogicException
{
    public function __construct(string $baseQueryHandlerClasses = QueryWizard::class)
    {
        parent::__construct("Invalid QueryHandler class. QueryHandler must extend `$baseQueryHandlerClasses`");
    }
}
