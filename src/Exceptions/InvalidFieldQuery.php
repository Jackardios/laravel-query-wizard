<?php

namespace Jackardios\QueryWizard\Exceptions;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class InvalidFieldQuery extends InvalidQueryHandler
{
    public Collection $unknownFields;
    public Collection $allowedFields;

    public function __construct(Collection $unknownFields, Collection $allowedFields)
    {
        $this->unknownFields = $unknownFields;
        $this->allowedFields = $allowedFields;

        $joinedUnknownFields = $unknownFields->implode(', ');
        $joinedAllowedFields = $allowedFields->implode(', ');
        $message = "Requested field(s) `{$joinedUnknownFields}` are not allowed. Allowed field(s) are `{$joinedAllowedFields}`.";

        parent::__construct(Response::HTTP_BAD_REQUEST, $message);
    }

    public static function fieldsNotAllowed(Collection $unknownFields, Collection $allowedFields): InvalidFieldQuery
    {
        return new static(...func_get_args());
    }
}
