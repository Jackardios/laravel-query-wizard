<?php

namespace Jackardios\QueryWizard\Exceptions;

use LogicException;

class DefaultFieldKeyIsNotDefined extends LogicException
{
    public function __construct()
    {
        parent::__construct("`defaultFieldKey` is not defined in QueryWizard.");
    }
}
