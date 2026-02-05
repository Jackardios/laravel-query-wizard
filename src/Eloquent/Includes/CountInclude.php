<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Includes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Jackardios\QueryWizard\Includes\AbstractInclude;

/**
 * Include for loading relationship counts via withCount().
 */
final class CountInclude extends AbstractInclude
{
    /**
     * Create a new count include.
     *
     * @param string $relation The relationship name
     * @param string|null $alias Optional alias for URL parameter name
     */
    public static function make(string $relation, ?string $alias = null): static
    {
        return new static($relation, $alias);
    }

    public function getType(): string
    {
        return 'count';
    }

    /**
     * @param Builder<Model>|Relation<Model, Model, mixed> $subject
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function apply(mixed $subject): mixed
    {
        return $subject->withCount($this->relation);
    }
}
