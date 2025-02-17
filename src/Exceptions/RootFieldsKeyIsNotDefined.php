<?php

namespace Jackardios\QueryWizard\Exceptions;

use LogicException;

class RootFieldsKeyIsNotDefined extends LogicException
{
    public function __construct()
    {
        parent::__construct("`rootFieldsKey` is not defined in QueryWizard.");
    }
}
