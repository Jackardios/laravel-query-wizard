<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Jackardios\QueryWizard\Eloquent\Filters\Concerns\HandlesRelationFiltering;
use Jackardios\QueryWizard\Eloquent\Filters\Concerns\ParsesRangeValues;
use Jackardios\QueryWizard\Filters\AbstractFilter;

/**
 * Base class for range-based filters (numeric, date, etc.).
 *
 * Supports dot notation for relation filtering (e.g., 'posts.created_at').
 *
 * Expects: ?filter[property][min]=X&filter[property][max]=Y, the keys named by
 * `$minKey` and `$maxKey`.
 *
 * @template TConstraint of array<mixed>
 *
 * @api
 */
abstract class AbstractRangeFilter extends AbstractFilter
{
    /** @use HandlesRelationFiltering<TConstraint> */
    use HandlesRelationFiltering;

    use ParsesRangeValues;

    /**
     * The request key of the lower bound; a subclass sets its own.
     *
     * @api
     */
    protected string $minKey = 'min';

    /**
     * The request key of the upper bound; a subclass sets its own.
     *
     * @api
     */
    protected string $maxKey = 'max';

    /**
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        return $this->applyToSubject($subject, $value);
    }

    public function validateValueShape(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return $this->invalidRangeValueShapeMessage();
        }

        if (array_is_list($value)) {
            if (count($value) !== 2) {
                return $this->invalidRangeValueShapeMessage();
            }

            foreach ($value as $boundaryValue) {
                if (is_array($boundaryValue)) {
                    return $this->invalidRangeValueShapeMessage();
                }
            }

            return null;
        }

        foreach ($value as $key => $boundaryValue) {
            if (($key !== $this->minKey && $key !== $this->maxKey) || is_array($boundaryValue)) {
                return $this->invalidRangeValueShapeMessage();
            }
        }

        return null;
    }

    /**
     * Read the request value into the bounds applyOnQuery() receives; null when neither bound is set.
     *
     * @return TConstraint|null
     *
     * @api
     */
    abstract protected function resolveConstraint(mixed $value): ?array;

    protected function invalidRangeValueShapeMessage(): string
    {
        return "Filter `{$this->getName()}` expects an array with `{$this->minKey}`/`{$this->maxKey}` keys or a flat list of two values.";
    }

    protected function supportsBooleanValues(): bool
    {
        return false;
    }
}
