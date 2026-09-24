<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Sorts\AbstractSort;
use Jackardios\QueryWizard\Support\EloquentSubject;

/**
 * Sort by relationship count.
 *
 * Uses `withCount` to add a count column, then sorts by it.
 *
 * Example:
 *   EloquentSort::count('posts')  // Sort by posts_count
 *   EloquentSort::count('posts')->alias('popularPosts')  // ?sort=popularPosts
 */
final class CountSort extends AbstractSort
{
    /**
     * Create a new count sort.
     *
     * @param  string  $relation  The relationship name to count
     * @param  string|null  $alias  Optional alias for URL parameter name
     */
    public static function make(string $relation, ?string $alias = null): static
    {
        return new self($relation, $alias);
    }

    public function getType(): string
    {
        return 'count';
    }

    /**
     * @param  Builder<Model>  $subject
     * @param  'asc'|'desc'  $direction
     * @return Builder<Model>
     */
    public function apply(mixed $subject, string $direction): mixed
    {
        $countColumn = EloquentSubject::aggregateAlias($this->property, 'count');

        if (! EloquentSubject::hasSelectAlias($subject, $countColumn)) {
            $subject->withCount($this->property);
        }

        $subject->orderBy($countColumn, $direction);

        return $subject;
    }
}
