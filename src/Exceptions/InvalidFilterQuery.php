<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class InvalidFilterQuery extends InvalidQuery
{
    /** @var Collection<int, string> */
    public readonly Collection $unknownFilters;

    /** @var Collection<int, string> */
    public readonly Collection $allowedFilters;

    /**
     * @param  Collection<int, string>  $unknownFilters
     * @param  Collection<int, string>  $allowedFilters
     */
    public function __construct(Collection $unknownFilters, Collection $allowedFilters, ?string $message = null)
    {
        $this->unknownFilters = $unknownFilters;
        $this->allowedFilters = $allowedFilters;

        if ($message === null) {
            $joinedUnknownFilters = $this->unknownFilters->implode(', ');

            if ($allowedFilters->isEmpty()) {
                $message = "Requested filter(s) `{$joinedUnknownFilters}` are not allowed. No filters are allowed.";
            } else {
                $joinedAllowedFilters = $this->allowedFilters->implode(', ');
                $message = "Requested filter(s) `{$joinedUnknownFilters}` are not allowed. Allowed filter(s) are `{$joinedAllowedFilters}`.";
            }
        }

        parent::__construct(Response::HTTP_BAD_REQUEST, $message);
    }

    /**
     * @param  Collection<int, string>  $unknownFilters
     * @param  Collection<int, string>  $allowedFilters
     */
    public static function filtersNotAllowed(Collection $unknownFilters, Collection $allowedFilters): self
    {
        return new self($unknownFilters, $allowedFilters);
    }

    public static function invalidFormat(string $details): self
    {
        return new self(
            collect([]),
            collect([]),
            "Invalid `filter` parameter format. {$details}"
        );
    }
}
