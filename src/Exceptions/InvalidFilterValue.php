<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Exception;

class InvalidFilterValue extends Exception
{
    public static function make(mixed $value): self
    {
        return new self("Filter value `{$value}` is invalid.");
    }
}
