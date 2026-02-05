<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class InvalidFieldQuery extends InvalidQuery
{
    /** @var Collection<int, string> */
    public readonly Collection $unknownFields;

    /** @var Collection<int, string> */
    public readonly Collection $allowedFields;

    /**
     * @param  Collection<int, string>  $unknownFields
     * @param  Collection<int, string>  $allowedFields
     */
    public function __construct(Collection $unknownFields, Collection $allowedFields)
    {
        $this->unknownFields = $unknownFields;
        $this->allowedFields = $allowedFields;

        $joinedUnknownFields = $unknownFields->implode(', ');

        if ($allowedFields->isEmpty()) {
            $message = "Requested field(s) `{$joinedUnknownFields}` are not allowed. No fields are allowed.";
        } else {
            $joinedAllowedFields = $allowedFields->implode(', ');
            $message = "Requested field(s) `{$joinedUnknownFields}` are not allowed. Allowed field(s) are `{$joinedAllowedFields}`.";
        }

        parent::__construct(Response::HTTP_BAD_REQUEST, $message);
    }

    /**
     * @param  Collection<int, string>  $unknownFields
     * @param  Collection<int, string>  $allowedFields
     */
    public static function fieldsNotAllowed(Collection $unknownFields, Collection $allowedFields): self
    {
        return new self($unknownFields, $allowedFields);
    }
}
