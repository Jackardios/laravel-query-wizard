<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class InvalidAppendQuery extends InvalidQuery
{
    /** @var Collection<int, string> */
    public readonly Collection $unknownAppends;

    /** @var Collection<int, string> */
    public readonly Collection $allowedAppends;

    /**
     * @param  Collection<int, string>  $unknownAppends
     * @param  Collection<int, string>  $allowedAppends
     */
    public function __construct(Collection $unknownAppends, Collection $allowedAppends)
    {
        $this->unknownAppends = $unknownAppends;
        $this->allowedAppends = $allowedAppends;

        $joinedUnknownAppends = $unknownAppends->implode(', ');

        if ($allowedAppends->isEmpty()) {
            $message = "Requested append(s) `{$joinedUnknownAppends}` are not allowed. No appends are allowed.";
        } else {
            $joinedAllowedAppends = $allowedAppends->implode(', ');
            $message = "Requested append(s) `{$joinedUnknownAppends}` are not allowed. Allowed append(s) are `{$joinedAllowedAppends}`.";
        }

        parent::__construct(Response::HTTP_BAD_REQUEST, $message);
    }

    /**
     * @param  Collection<int, string>  $unknownAppends
     * @param  Collection<int, string>  $allowedAppends
     */
    public static function appendsNotAllowed(Collection $unknownAppends, Collection $allowedAppends): self
    {
        return new self($unknownAppends, $allowedAppends);
    }
}
