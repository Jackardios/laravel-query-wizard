<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;

/**
 * Reaches the builders behind a wizard subject, which is either an Eloquent
 * builder or a relation (`EloquentQueryWizard::for($user->posts())`).
 *
 * @internal
 */
final class EloquentSubject
{
    /**
     * The Eloquent builder a subject applies its constraints to.
     *
     * A relation shares this builder, so constraints added to it stay scoped
     * to the relation.
     *
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     * @return Builder<Model>
     */
    public static function builder(Builder|Relation $subject): Builder
    {
        return $subject instanceof Relation ? $subject->getQuery() : $subject;
    }

    /**
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     */
    public static function baseQuery(Builder|Relation $subject): QueryBuilder
    {
        return self::builder($subject)->getQuery();
    }

    /**
     * Whether the subject already selects a `withCount()`/`withExists()` style aggregate of the relation.
     *
     * @param  'count'|'exists'  $function
     */
    public static function selectsAggregate(mixed $subject, string $relation, string $function): bool
    {
        return self::hasSelectAlias($subject, self::aggregateAlias($relation, $function));
    }

    /**
     * The alias Laravel gives `withCount($relation)` / `withExists($relation)`.
     */
    public static function aggregateAlias(string $relation, string $function): string
    {
        return Str::snake((string) preg_replace('/[^[:alnum:][:space:]_]/u', '', sprintf('%s %s %s', $relation, $function, '*')));
    }

    /**
     * Whether an expression selected by the subject ends with `as <alias>`; false for other subjects.
     */
    public static function hasSelectAlias(mixed $subject, string $alias): bool
    {
        if (! $subject instanceof Builder && ! $subject instanceof Relation) {
            return false;
        }

        $query = self::baseQuery($subject);
        $grammar = $query->getGrammar();
        $suffix = ' as '.$grammar->wrap($alias);

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
