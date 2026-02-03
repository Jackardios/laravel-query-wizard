<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Symfony\Component\HttpFoundation\Response;

abstract class QueryLimitExceeded extends InvalidQuery
{
    public function __construct(string $message)
    {
        parent::__construct(Response::HTTP_BAD_REQUEST, $message);
    }
}
