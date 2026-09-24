<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class InvalidSortQuery extends InvalidQuery
{
    /** @var Collection<int, string> */
    public readonly Collection $unknownSorts;

    /** @var Collection<int, string> */
    public readonly Collection $allowedSorts;

    /**
     * @param  Collection<int, string>  $unknownSorts
     * @param  Collection<int, string>  $allowedSorts
     */
    public function __construct(
        Collection $unknownSorts,
        Collection $allowedSorts,
        ?string $message = null,
        string $errorCode = 'sort_not_allowed'
    ) {
        $this->unknownSorts = $unknownSorts;
        $this->allowedSorts = $allowedSorts;

        if ($message === null) {
            $joinedUnknownSorts = $unknownSorts->implode(', ');

            if ($allowedSorts->isEmpty()) {
                $message = "Requested sort(s) `{$joinedUnknownSorts}` are not allowed. No sorts are allowed.";
            } else {
                $joinedAllowedSorts = $allowedSorts->implode(', ');
                $message = "Requested sort(s) `{$joinedUnknownSorts}` are not allowed. Allowed sort(s) are `{$joinedAllowedSorts}`.";
            }
        }

        parent::__construct(Response::HTTP_BAD_REQUEST, $message, errorCode: $errorCode, parameter: self::parameterName('sorts'));
    }

    /**
     * @param  Collection<int, string>  $unknownSorts
     * @param  Collection<int, string>  $allowedSorts
     */
    public static function sortsNotAllowed(Collection $unknownSorts, Collection $allowedSorts): self
    {
        return new self($unknownSorts, $allowedSorts);
    }

    public static function invalidFormat(?string $details = null): self
    {
        $parameter = self::parameterName('sorts');
        $message = "The `{$parameter}` parameter has an invalid format.";

        if ($details !== null && $details !== '') {
            $message .= ' '.$details;
        }

        return new self(collect(), collect(), $message, 'invalid_sort_format');
    }
}
