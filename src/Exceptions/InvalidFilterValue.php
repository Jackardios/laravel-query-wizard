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
        return new self(
            Response::HTTP_BAD_REQUEST,
            $filterName !== ''
                ? "Filter value `{$value}` is invalid for filter `{$filterName}`."
                : "Filter value `{$value}` is invalid.",
            $filterName,
            $value
        );
    }
}
