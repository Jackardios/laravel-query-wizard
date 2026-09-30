<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Jackardios\QueryWizard\Contracts\FilterInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-consistent-constructor
 */
class InvalidFilterValue extends InvalidQuery
{
    public const ERROR_CODE = 'invalid_filter_value';

    private const MAX_ECHOED_VALUE_LENGTH = 100;

    /**
     * The filter's public name, or null when the value was read without a filter.
     */
    public readonly ?string $filterName;

    public readonly mixed $filterValue;

    /**
     * What was expected instead, e.g. "Expected a boolean.", or null.
     */
    public readonly ?string $reason;

    /**
     * @internal Use make(), which writes the message from the value, the filter and the reason.
     */
    public function __construct(
        string $message,
        ?string $filterName = null,
        mixed $filterValue = null,
        ?string $reason = null
    ) {
        parent::__construct(Response::HTTP_BAD_REQUEST, $message, errorCode: self::ERROR_CODE, parameter: self::parameterName('filters'));
        $this->filterName = $filterName;
        $this->filterValue = $filterValue;
        $this->reason = $reason;
    }

    /**
     * The 400 a filter throws for a value it cannot read. Called on a subclass,
     * it returns an instance of that subclass.
     *
     * @param  string|FilterInterface|null  $filter  The filter or its public name
     * @param  string|null  $reason  What was expected instead; appended to the message
     *
     * @api
     */
    public static function make(mixed $value, string|FilterInterface|null $filter = null, ?string $reason = null): static
    {
        $filterName = $filter instanceof FilterInterface ? $filter->getName() : $filter;
        $filterName = $filterName === '' ? null : $filterName;
        $valueString = self::shorten(self::formatValue($value));

        $message = $filterName !== null
            ? "Filter value `{$valueString}` is invalid for filter `{$filterName}`."
            : "Filter value `{$valueString}` is invalid.";

        if ($reason !== null && $reason !== '') {
            $message .= ' '.$reason;
        }

        return new static($message, $filterName, $value, $reason);
    }

    private static function shorten(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');

        return mb_strlen($value) > self::MAX_ECHOED_VALUE_LENGTH
            ? mb_substr($value, 0, self::MAX_ECHOED_VALUE_LENGTH).'…'
            : $value;
    }

    private static function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_float($value) && is_nan($value)) {
            return 'NAN';
        }
        if (is_bool($value) || $value === null) {
            return match ($value) {
                true => 'true',
                false => 'false',
                null => 'null',
            };
        }
        if (is_scalar($value)) {
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
