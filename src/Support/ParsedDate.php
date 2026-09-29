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

    /**
     * The comparison that matches everything up to and including the value: a
     * date names its whole day, so it ends before the next day starts.
     *
     * `<` and `>=` against the value itself need no bound: a date then names the
     * start of its day.
     *
     * @return array{0: '<'|'<=', 1: self}
     */
    public function upToBound(): array
    {
        $end = $this->endOfNamedPeriod();

        return [$end->dateOnly ? '<' : '<=', $end];
    }

    /**
     * The comparison that matches everything after the value: after a date
     * means from the start of the next day.
     *
     * @return array{0: '>'|'>=', 1: self}
     */
    public function afterBound(): array
    {
        $end = $this->endOfNamedPeriod();

        return [$end->dateOnly ? '>=' : '>', $end];
    }

    /**
     * The start of the next day for a date, or the value itself for an instant.
     *
     * 10000-01-01 sorts before every four-digit date as text, so the last day
     * of year 9999 ends at its last second instead.
     */
    private function endOfNamedPeriod(): self
    {
        if (! $this->dateOnly) {
            return $this;
        }

        $nextDay = $this->value->modify('+1 day')->setTime(0, 0);

        return (int) $nextDay->format('Y') > 9999
            ? new self($this->value->setTime(23, 59, 59), false)
            : new self($nextDay, true);
    }
}
