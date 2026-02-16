<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class InvalidFilterValue extends InvalidQuery
{
    public readonly string $filterName;

    public readonly mixed $filterValue;

    public function __construct(int $statusCode, string $message, string $filterName = '', mixed $filterValue = null)
    {
        parent::__construct($statusCode, $message);
        $this->filterName = $filterName;
        $this->filterValue = $filterValue;
    }

    public static function make(mixed $value, string $filterName = ''): self
    {
        $valueString = self::formatValue($value);

        return new self(
            Response::HTTP_BAD_REQUEST,
            $filterName !== ''
                ? "Filter value `{$valueString}` is invalid for filter `{$filterName}`."
                : "Filter value `{$valueString}` is invalid.",
            $filterName,
            $value
        );
    }

    private static function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }
        if (is_array($value)) {
            return 'array';
        }
        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : get_class($value);
        }

        return gettype($value);
    }
}
