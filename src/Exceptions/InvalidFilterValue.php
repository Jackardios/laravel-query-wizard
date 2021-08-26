<?php

namespace Jackardios\QueryWizard\Exceptions;

use Exception;

class InvalidFilterValue extends Exception
{
    public static function make($value): InvalidFilterValue
    {
        return new static("Filter value `{$value}` is invalid.");
    }
}
