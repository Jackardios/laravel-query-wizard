<?php

namespace Jackardios\QueryWizard\Exceptions;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class InvalidSortQuery extends InvalidQueryHandler
{
    public Collection $unknownSorts;
    public Collection $allowedSorts;

    public function __construct(Collection $unknownSorts, Collection $allowedSorts)
    {
        $this->unknownSorts = $unknownSorts;
        $this->allowedSorts = $allowedSorts;

        $joinedAllowedSorts = $allowedSorts->implode(', ');
        $joinedUnknownSorts = $unknownSorts->implode(', ');
        $message = "Requested sort(s) `{$joinedUnknownSorts}` is not allowed. Allowed sort(s) are `{$joinedAllowedSorts}`.";

        parent::__construct(Response::HTTP_BAD_REQUEST, $message);
    }

    public static function sortsNotAllowed(Collection $unknownSorts, Collection $allowedSorts): InvalidSortQuery
    {
        return new static(...func_get_args());
    }
}
