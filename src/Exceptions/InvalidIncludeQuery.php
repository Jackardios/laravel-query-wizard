<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class InvalidIncludeQuery extends InvalidQuery
{
    /** @var Collection<int, string> */
    public Collection $unknownIncludes;

    /** @var Collection<int, string> */
    public Collection $allowedIncludes;

    /**
     * @param Collection<int, string> $unknownIncludes
     * @param Collection<int, string> $allowedIncludes
     */
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

    /**
     * @param Collection<int, string> $unknownIncludes
     * @param Collection<int, string> $allowedIncludes
     */
    public static function includesNotAllowed(Collection $unknownIncludes, Collection $allowedIncludes): self
    {
        return new self($unknownIncludes, $allowedIncludes);
    }
}
