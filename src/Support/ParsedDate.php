<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use DateTimeImmutable;

/**
 * A date read from a filter value.
 *
 * @api
 */
final class ParsedDate
{
    /**
     * @param  DateTimeImmutable  $value  The instant, in the timezone the value was parsed for
     * @param  bool  $dateOnly  Whether the input named a whole day (Y-m-d) rather than an instant
     */
    public function __construct(
        public readonly DateTimeImmutable $value,
        public readonly bool $dateOnly,
    ) {}
}
