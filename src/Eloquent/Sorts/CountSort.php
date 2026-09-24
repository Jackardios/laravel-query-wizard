<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
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
        $countColumn = Str::snake($this->property).'_count';

        if (! $this->hasCountColumn($subject, $countColumn)) {
            $subject->withCount($this->property);
        }

        $subject->orderBy($countColumn, $direction);

        return $subject;
    }

    private function hasCountColumn(mixed $subject, string $alias): bool
    {
        if (! $subject instanceof Builder && ! $subject instanceof Relation) {
            return false;
        }

        $query = EloquentSubject::baseQuery($subject);
        $grammar = $query->getGrammar();
        $wrappedAlias = $grammar->wrap($alias);
        $suffix = ' as '.$wrappedAlias;

        foreach ($query->columns ?? [] as $column) {
            if ($column instanceof Expression) {
                $sql = $column->getValue($grammar);
                if (is_string($sql) && str_ends_with($sql, $suffix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
