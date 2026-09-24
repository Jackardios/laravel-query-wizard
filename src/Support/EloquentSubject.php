<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;

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
}
