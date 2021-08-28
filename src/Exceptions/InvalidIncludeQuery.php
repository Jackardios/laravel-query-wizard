<?php

namespace Jackardios\QueryWizard\Exceptions;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class InvalidIncludeQuery extends InvalidQuery
{
    public Collection $unknownIncludes;
    public Collection $allowedIncludes;

    public function __construct(Collection $unknownIncludes, Collection $allowedIncludes)
    {
        $this->unknownIncludes = $unknownIncludes;
        $this->allowedIncludes = $allowedIncludes;

        $joinedUnknownIncludes = $unknownIncludes->implode(', ');

        $message = "Requested include(s) `{$joinedUnknownIncludes}` are not allowed. ";

        if ($allowedIncludes->count()) {
            $joinedAllowedIncludes = $allowedIncludes->implode(', ');
            $message .= "Allowed include(s) are `{$joinedAllowedIncludes}`.";
        } else {
            $message .= 'There are no allowed includes.';
        }

        parent::__construct(Response::HTTP_BAD_REQUEST, $message);
    }

    public static function includesNotAllowed(Collection $unknownIncludes, Collection $allowedIncludes): InvalidIncludeQuery
    {
        return new static(...func_get_args());
    }
}
