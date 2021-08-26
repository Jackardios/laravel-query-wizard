<?php

namespace Jackardios\QueryWizard\Exceptions;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class InvalidFilterQuery extends InvalidQueryHandler
{
    public Collection $unknownFilters;
    public Collection $allowedFilters;

    public function __construct(Collection $unknownFilters, Collection $allowedFilters)
    {
        $this->unknownFilters = $unknownFilters;
        $this->allowedFilters = $allowedFilters;

        $joinedUnknownFilters = $this->unknownFilters->implode(', ');
        $joinedAllowedFilters = $this->allowedFilters->implode(', ');
        $message = "Requested filter(s) `{$joinedUnknownFilters}` are not allowed. Allowed filter(s) are `{$joinedAllowedFilters}`.";

        parent::__construct(Response::HTTP_BAD_REQUEST, $message);
    }

    public static function filtersNotAllowed(Collection $unknownFilters, Collection $allowedFilters): InvalidFilterQuery
    {
        return new static(...func_get_args());
    }
}
