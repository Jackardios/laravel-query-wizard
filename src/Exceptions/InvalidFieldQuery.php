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
    public function __construct(
        Collection $unknownFields,
        Collection $allowedFields,
        ?string $message = null,
        string $errorCode = 'field_not_allowed'
    ) {
        $this->unknownFields = $unknownFields;
        $this->allowedFields = $allowedFields;

        if ($message === null) {
            $joinedUnknownFields = $unknownFields->implode(', ');

            if ($allowedFields->isEmpty()) {
                $message = "Requested field(s) `{$joinedUnknownFields}` are not allowed. No fields are allowed.";
            } else {
                $joinedAllowedFields = $allowedFields->implode(', ');
                $message = "Requested field(s) `{$joinedUnknownFields}` are not allowed. Allowed field(s) are `{$joinedAllowedFields}`.";
            }
        }

        parent::__construct(Response::HTTP_BAD_REQUEST, $message, errorCode: $errorCode, parameter: self::parameterName('fields'));
    }

    /**
     * @param  Collection<int, string>  $unknownFields
     * @param  Collection<int, string>  $allowedFields
     */
    public static function fieldsNotAllowed(Collection $unknownFields, Collection $allowedFields): self
    {
        return new self($unknownFields, $allowedFields);
    }

    public static function invalidFormat(?string $details = null): self
    {
        $parameter = self::parameterName('fields');
        $message = "The `{$parameter}` parameter has an invalid format.";

        if ($details !== null && $details !== '') {
            $message .= ' '.$details;
        }

        return new self(collect(), collect(), $message, 'invalid_field_format');
    }
}
