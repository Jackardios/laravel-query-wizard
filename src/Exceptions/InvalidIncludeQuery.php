<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class InvalidIncludeQuery extends InvalidQuery
{
    /** @var Collection<int, string> */
    public readonly Collection $unknownIncludes;

    /** @var Collection<int, string> */
    public readonly Collection $allowedIncludes;

    /**
     * @param  Collection<int, string>  $unknownIncludes
     * @param  Collection<int, string>  $allowedIncludes
     */
    public function __construct(
        Collection $unknownIncludes,
        Collection $allowedIncludes,
        ?string $message = null,
        string $errorCode = 'include_not_allowed'
    ) {
        $this->unknownIncludes = $unknownIncludes;
        $this->allowedIncludes = $allowedIncludes;

        if ($message === null) {
            $joinedUnknownIncludes = $unknownIncludes->implode(', ');

            $message = "Requested include(s) `{$joinedUnknownIncludes}` are not allowed. ";

            if ($allowedIncludes->count()) {
                $joinedAllowedIncludes = $allowedIncludes->implode(', ');
                $message .= "Allowed include(s) are `{$joinedAllowedIncludes}`.";
            } else {
                $message .= 'No includes are allowed.';
            }
        }

        parent::__construct(Response::HTTP_BAD_REQUEST, $message, errorCode: $errorCode, parameter: self::parameterName('includes'));
    }

    /**
     * @param  Collection<int, string>  $unknownIncludes
     * @param  Collection<int, string>  $allowedIncludes
     */
    public static function includesNotAllowed(Collection $unknownIncludes, Collection $allowedIncludes): self
    {
        return new self($unknownIncludes, $allowedIncludes);
    }

    public static function invalidFormat(?string $details = null): self
    {
        $parameter = self::parameterName('includes');
        $message = "The `{$parameter}` parameter has an invalid format.";

        if ($details !== null && $details !== '') {
            $message .= ' '.$details;
        }

        return new self(collect(), collect(), $message, 'invalid_include_format');
    }
}
