<?php

namespace Jackardios\QueryWizard\Exceptions;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class InvalidAppendQuery extends InvalidQuery
{
    public Collection $unknownAppends;
    public Collection $allowedAppends;

    public function __construct(Collection $unknownAppends, Collection $allowedAppends)
    {
        $this->unknownAppends = $unknownAppends;
        $this->allowedAppends = $allowedAppends;

        $joinedUnknownAppends = $unknownAppends->implode(', ');
        $joinedAllowedAppends = $allowedAppends->implode(', ');
        $message = "Requested append(s) `{$joinedUnknownAppends}` are not allowed. Allowed append(s) are `{$joinedAllowedAppends}`.";

        parent::__construct(Response::HTTP_BAD_REQUEST, $message);
    }

    public static function appendsNotAllowed(Collection $unknownAppends, Collection $allowedAppends): InvalidAppendQuery
    {
        return new static(...func_get_args());
    }
}
