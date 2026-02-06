<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Sorts\AbstractSort;

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
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $subject
     * @param  'asc'|'desc'  $direction
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(mixed $subject, string $direction): mixed
    {
        $countColumn = Str::snake($this->property).'_count';

        if (! $this->hasCountColumn($subject, $countColumn)) {
            $subject->withCount($this->property);
        }

        $subject->orderBy($countColumn, $direction);

        return $subject;
    }

    private function hasCountColumn(mixed $subject, string $alias): bool
    {
        if (! $subject instanceof Builder) {
            return false;
        }

        foreach ($subject->getQuery()->columns ?? [] as $column) {
            if ($column instanceof \Illuminate\Database\Query\Expression) {
                $sql = $column->getValue($subject->getQuery()->getGrammar());
                if (is_string($sql) && str_ends_with($sql, " as \"{$alias}\"")) {
                    return true;
                }
            }
        }

        return false;
    }
}
