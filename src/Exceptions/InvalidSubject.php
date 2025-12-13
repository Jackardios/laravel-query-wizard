<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use InvalidArgumentException;

class InvalidSubject extends InvalidArgumentException
{
    public static function make(mixed $subject): self
    {
        return new self(
            sprintf(
                'Subject %s is invalid.',
                is_object($subject)
                    ? sprintf('class `%s`', get_class($subject))
                    : sprintf('type `%s`', gettype($subject))
            )
        );
    }
}
