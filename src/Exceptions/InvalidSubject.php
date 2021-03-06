<?php

namespace Jackardios\QueryWizard\Exceptions;

use InvalidArgumentException;

class InvalidSubject extends InvalidArgumentException
{
    public static function make($subject): InvalidSubject
    {
        return new static(
            sprintf(
                'Subject %s is invalid.',
                is_object($subject)
                    ? sprintf('class `%s`', get_class($subject))
                    : sprintf('type `%s`', gettype($subject))
            )
        );
    }
}
