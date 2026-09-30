<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The JSON request body the parameters are read from (request_data_source
 * `body`) is not a JSON object, or holds a number that overflows to infinity.
 */
class InvalidRequestBody extends InvalidQuery
{
    /**
     * @internal Use the named constructors.
     */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct(Response::HTTP_BAD_REQUEST, $message, $previous, errorCode: 'invalid_request_body');
    }

    public static function malformedJson(string $details, ?Throwable $previous = null): self
    {
        return new self("The request body is not valid JSON: {$details}.", $previous);
    }

    public static function notAnObject(): self
    {
        return new self('The request body must be a JSON object.');
    }

    public static function nonFiniteNumber(): self
    {
        return new self('The request body contains a number too large to read.');
    }
}
