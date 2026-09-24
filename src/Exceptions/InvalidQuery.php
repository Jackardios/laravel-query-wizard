<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Base class for every rejected query.
 *
 * `errorCode` is a stable machine-readable reason (e.g. `filter_not_allowed`)
 * and `parameter` the request parameter it refers to, as configured under
 * `query-wizard.parameters` (e.g. `filter`), or null when not tied to one.
 */
abstract class InvalidQuery extends HttpException
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        int $statusCode,
        string $message = '',
        ?Throwable $previous = null,
        array $headers = [],
        int $code = 0,
        public readonly string $errorCode = 'invalid_query',
        public readonly ?string $parameter = null,
    ) {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    /**
     * Request parameter name configured for a parameter group.
     *
     * @param  'filters'|'sorts'|'includes'|'fields'|'appends'  $group
     */
    protected static function parameterName(string $group): string
    {
        $defaults = [
            'filters' => 'filter',
            'sorts' => 'sort',
            'includes' => 'include',
            'fields' => 'fields',
            'appends' => 'append',
        ];

        try {
            $name = config("query-wizard.parameters.{$group}");
        } catch (Throwable) {
            $name = null;
        }

        return is_string($name) && $name !== '' ? $name : $defaults[$group];
    }
}
