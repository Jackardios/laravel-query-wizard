<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * The JSON request body the parameters are read from (request_data_source
 * `body`) is not a JSON object.
 */
class InvalidRequestBody extends InvalidQuery
{
    public function __construct(string $message)
    {
        parent::__construct(Response::HTTP_BAD_REQUEST, $message, errorCode: 'invalid_request_body');
    }

    public static function malformedJson(string $details): self
    {
        return new self("The request body is not valid JSON: {$details}.");
    }

    public static function notAnObject(): self
    {
        return new self('The request body must be a JSON object.');
    }
}
