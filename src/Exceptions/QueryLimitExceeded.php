<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Symfony\Component\HttpFoundation\Response;

abstract class QueryLimitExceeded extends InvalidQuery
{
    public function __construct(string $message, string $errorCode = 'query_limit_exceeded', ?string $parameter = null)
    {
        parent::__construct(Response::HTTP_BAD_REQUEST, $message, errorCode: $errorCode, parameter: $parameter);
    }
}
