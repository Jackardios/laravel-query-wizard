<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvalidFilterQuery extends InvalidQuery
{
    /** @var Collection<int, string> */
    public readonly Collection $unknownFilters;

    /** @var Collection<int, string> */
    public readonly Collection $allowedFilters;

    /**
     * @param  Collection<int, string>  $unknownFilters
     * @param  Collection<int, string>  $allowedFilters
     *
     * @internal Use filtersNotAllowed() or invalidFormat().
     */
    public function __construct(
        Collection $unknownFilters,
        Collection $allowedFilters,
        ?string $message = null,
        string $errorCode = 'filter_not_allowed',
        ?Throwable $previous = null
    ) {
        $this->unknownFilters = $unknownFilters;
        $this->allowedFilters = $allowedFilters;

        if ($message === null) {
            $joinedUnknownFilters = $this->unknownFilters->implode(', ');

            if ($allowedFilters->isEmpty()) {
                $message = "Requested filter(s) `{$joinedUnknownFilters}` are not allowed. No filters are allowed.";
            } else {
                $joinedAllowedFilters = $this->allowedFilters->implode(', ');
                $message = "Requested filter(s) `{$joinedUnknownFilters}` are not allowed. Allowed filter(s) are `{$joinedAllowedFilters}`.";
            }
        }

        parent::__construct(Response::HTTP_BAD_REQUEST, $message, $previous, errorCode: $errorCode, parameter: self::parameterName('filters'));
    }

    /**
     * @param  Collection<int, string>  $unknownFilters
     * @param  Collection<int, string>  $allowedFilters
     */
    public static function filtersNotAllowed(Collection $unknownFilters, Collection $allowedFilters): self
    {
        return new self($unknownFilters, $allowedFilters);
    }

    public static function invalidFormat(?string $details = null, ?Throwable $previous = null): self
    {
        $parameter = self::parameterName('filters');
        $message = "The `{$parameter}` parameter has an invalid format.";

        if ($details !== null && $details !== '') {
            $message .= ' '.$details;
        }

        return new self(collect(), collect(), $message, 'invalid_filter_format', $previous);
    }
}
