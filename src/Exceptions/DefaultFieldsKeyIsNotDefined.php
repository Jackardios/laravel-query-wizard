<?php

namespace Jackardios\QueryWizard\Exceptions;

use LogicException;

class DefaultFieldsKeyIsNotDefined extends LogicException
{
    public function __construct()
    {
        parent::__construct("`defaultFieldsKey` is not defined in QueryWizard.");
    }
}
