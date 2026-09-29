<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use RuntimeException;

/**
 * Thrown by ParameterParser when a list names more distinct items than allowed.
 *
 * @internal
 */
final class ListLimitExceeded extends RuntimeException
{
    public function __construct(public readonly int $count)
    {
        parent::__construct("The list has more than {$count} items.");
    }
}
