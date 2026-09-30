<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Exceptions;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvalidAppendQuery extends InvalidQuery
{
    public const NOT_ALLOWED = 'append_not_allowed';

    public const INVALID_FORMAT = 'invalid_append_format';

    /** @var Collection<int, string> */
    public readonly Collection $unknownAppends;

    /** @var Collection<int, string> */
    public readonly Collection $allowedAppends;

    /**
     * @param  Collection<int, string>  $unknownAppends
     * @param  Collection<int, string>  $allowedAppends
     *
     * @internal Use appendsNotAllowed() or invalidFormat().
     */
    public function __construct(
        Collection $unknownAppends,
        Collection $allowedAppends,
        ?string $message = null,
        string $errorCode = self::NOT_ALLOWED,
        ?Throwable $previous = null
    ) {
        $this->unknownAppends = $unknownAppends;
        $this->allowedAppends = $allowedAppends;

        if ($message === null) {
            $joinedUnknownAppends = $unknownAppends->implode(', ');

            if ($allowedAppends->isEmpty()) {
                $message = "Requested append(s) `{$joinedUnknownAppends}` are not allowed. No appends are allowed.";
            } else {
                $joinedAllowedAppends = $allowedAppends->implode(', ');
                $message = "Requested append(s) `{$joinedUnknownAppends}` are not allowed. Allowed append(s) are `{$joinedAllowedAppends}`.";
            }
        }

        parent::__construct(Response::HTTP_BAD_REQUEST, $message, $previous, errorCode: $errorCode, parameter: self::parameterName('appends'));
    }

    /**
     * @param  Collection<int, string>  $unknownAppends
     * @param  Collection<int, string>  $allowedAppends
     */
    public static function appendsNotAllowed(Collection $unknownAppends, Collection $allowedAppends): self
    {
        return new self($unknownAppends, $allowedAppends);
    }

    public static function invalidFormat(?string $details = null, ?Throwable $previous = null): self
    {
        $parameter = self::parameterName('appends');
        $message = "The `{$parameter}` parameter has an invalid format.";

        if ($details !== null && $details !== '') {
            $message .= ' '.$details;
        }

        return new self(collect(), collect(), $message, self::INVALID_FORMAT, $previous);
    }
}
