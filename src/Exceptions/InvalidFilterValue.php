<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Jackardios\QueryWizard\Contracts\FilterInterface;
use Symfony\Component\HttpFoundation\Response;

class InvalidFilterValue extends InvalidQuery
{
    public readonly string $filterName;

    public readonly mixed $filterValue;

    /**
     * What was expected instead, e.g. "Expected a boolean.", or null.
     */
    public readonly ?string $reason;

    public function __construct(
        int $statusCode,
        string $message,
        string $filterName = '',
        mixed $filterValue = null,
        ?string $reason = null
    ) {
        parent::__construct($statusCode, $message, errorCode: 'invalid_filter_value', parameter: self::parameterName('filters'));
        $this->filterName = $filterName;
        $this->filterValue = $filterValue;
        $this->reason = $reason;
    }

    /**
     * @param  string|FilterInterface  $filter  The filter or its public name
     * @param  string|null  $reason  What was expected instead; appended to the message
     */
    public static function make(mixed $value, string|FilterInterface $filter = '', ?string $reason = null): self
    {
        $filterName = $filter instanceof FilterInterface ? $filter->getName() : $filter;
        $valueString = self::formatValue($value);

        $message = $filterName !== ''
            ? "Filter value `{$valueString}` is invalid for filter `{$filterName}`."
            : "Filter value `{$valueString}` is invalid.";

        if ($reason !== null && $reason !== '') {
            $message .= ' '.$reason;
        }

        return new self(Response::HTTP_BAD_REQUEST, $message, $filterName, $value, $reason);
    }

    private static function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_float($value) && is_nan($value)) {
            return 'NAN';
        }
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }
        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

            return $json !== false ? $json : 'array';
        }
        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : get_class($value);
        }

        return gettype($value);
    }
}
