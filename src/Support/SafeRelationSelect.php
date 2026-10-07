<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * Narrows eager-load queries to the relation sparse fields.
 *
 * The technical keys Eloquent needs to match the relations are added to the
 * selected columns. Only relation types with predictable key requirements are
 * narrowed.
 *
 * @internal
 */
final class SafeRelationSelect
{
    /**
     * Narrow an eager-load relation query to the fieldset and the keys it needs.
     *
     * Runs as the eager-load constraint on the relation Eloquent already built,
     * when the relation query already knows its own eager loads (the related
     * model's `$with` and nested includes), so the columns those need are
     * selected too. A query that already selects columns (the related model's
     * `$withCount`, a select in the relation definition or in a developer
     * constraint) is left as it is, and so is one that joins another table,
     * unless the relation is a BelongsToThrough; the fields outside the fieldset
     * are hidden after loading.
     *
     * @param  array<string>  $fields
     */
    public static function apply(mixed $query, array $fields): void
    {
        if (! $query instanceof Relation || ! self::isSelectable($query) || self::hasModelAppends($query)) {
            return;
        }

        if ($query->getQuery()->getQuery()->columns !== null) {
            return;
        }

        // A join in the relation definition makes the columns anyone's: a fieldset
        // name may belong to the joined table, and a bare name may be ambiguous.
        if (! self::isBelongsToThrough($query) && ! empty($query->getQuery()->getQuery()->joins)) {
            return;
        }

        $columns = [];
        self::appendColumns($columns, $fields);
        self::appendColumns($columns, self::relatedRequiredColumns($query), true);

        $eagerLoadNames = self::topLevelEagerLoadNames($query->getQuery()->getEagerLoads());

        if ($eagerLoadNames !== []) {
            $eagerLoadColumns = self::parentColumnsForEagerLoads(new RelationResolver($query->getRelated()), $eagerLoadNames);

            if ($eagerLoadColumns === null) {
                return;
            }

            self::appendColumns($columns, $eagerLoadColumns);
        }

        $query->select(self::qualifyColumns($query, $columns));
    }

    /**
     * Parent columns the given eager loads need to match their models.
     *
     * Returns null when one of the relations can't be resolved or its key
     * columns are unknown; the parent query then has to keep all its columns.
     *
     * @param  array<string>  $relationNames  Top-level relation names
     * @return array<string>|null
     */
    public static function parentColumnsForEagerLoads(RelationResolver $relations, array $relationNames): ?array
    {
        $columns = [];

        foreach ($relationNames as $relationName) {
            $relation = $relations->resolve($relationName);
            $required = $relation === null ? [] : self::parentRequiredColumns($relation);

            if ($required === []) {
                return null;
            }

            self::appendColumns($columns, $required, true);
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $eagerLoads
     * @return array<string>
     */
    public static function topLevelEagerLoadNames(array $eagerLoads): array
    {
        return array_values(array_filter(
            array_keys($eagerLoads),
            static fn (string $name): bool => ! str_contains($name, '.')
        ));
    }

    /**
     * Add columns that are not in the target yet, skipping blanks and `*`.
     *
     * @param  array<string>  $target
     * @param  array<string>  $source
     * @param  bool  $normalize  Strip the table from qualified columns
     */
    public static function appendColumns(array &$target, array $source, bool $normalize = false): void
    {
        $present = array_fill_keys($target, true);

        foreach ($source as $column) {
            if (! is_string($column)) {
                continue;
            }

            $column = trim($column);
            if ($column === '' || $column === '*') {
                continue;
            }

            if ($normalize && str_contains($column, '.')) {
                $column = Str::afterLast($column, '.');
            }

            if ($column === '' || isset($present[$column])) {
                continue;
            }

            $present[$column] = true;
            $target[] = $column;
        }
    }

    /**
     * @param  Relation<Model, Model, mixed>  $relation
     */
    private static function isSelectable(Relation $relation): bool
    {
        if ($relation instanceof MorphTo) {
            return false;
        }

        return $relation instanceof BelongsTo
            || $relation instanceof HasOneOrMany
            || self::isBelongsToThrough($relation);
    }

    /**
     * Whether the related model has built-in appends, whose accessors may
     * depend on attributes outside the sparse fieldset.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     */
    private static function hasModelAppends(Relation $relation): bool
    {
        return ! empty($relation->getRelated()->getAppends());
    }

    /**
     * Columns that must exist on parent models to load this relation.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     * @return array<string>
     */
    private static function parentRequiredColumns(Relation $relation): array
    {
        if ($relation instanceof MorphTo) {
            return [
                $relation->getForeignKeyName(),
                $relation->getMorphType(),
            ];
        }

        if ($relation instanceof BelongsTo) {
            return [$relation->getForeignKeyName()];
        }

        if ($relation instanceof HasOneOrMany) {
            return [$relation->getLocalKeyName()];
        }

        if (self::isBelongsToThrough($relation)) {
            $firstForeignKeyName = self::callWithoutArguments($relation, 'getFirstForeignKeyName');

            return $firstForeignKeyName !== null ? [$firstForeignKeyName] : [];
        }

        $parentKeyName = self::callWithoutArguments($relation, 'getParentKeyName');
        if ($parentKeyName !== null) {
            return [$parentKeyName];
        }

        $localKeyName = self::callWithoutArguments($relation, 'getLocalKeyName');
        if ($localKeyName !== null) {
            return [$localKeyName];
        }

        return [];
    }

    /**
     * Columns that must exist in relation select for eager matching.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     * @return array<string>
     */
    private static function relatedRequiredColumns(Relation $relation): array
    {
        if ($relation instanceof BelongsTo) {
            return [$relation->getOwnerKeyName()];
        }

        if ($relation instanceof MorphOneOrMany) {
            return [
                $relation->getForeignKeyName(),
                $relation->getMorphType(),
            ];
        }

        if ($relation instanceof HasOneOrMany) {
            return [$relation->getForeignKeyName()];
        }

        return [];
    }

    /**
     * Detect BelongsToThrough-style relations without requiring the optional package.
     *
     * The package uses getFirstForeignKeyName() for eager matching and a
     * model-aware getLocalKeyName(Model $model) accessor.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     */
    private static function isBelongsToThrough(Relation $relation): bool
    {
        if (is_a($relation, 'Znck\Eloquent\\Relations\\BelongsToThrough')) {
            return true;
        }

        return method_exists($relation, 'getFirstForeignKeyName')
            && method_exists($relation, 'getQualifiedFirstLocalKeyName')
            && self::requiresArguments($relation, 'getLocalKeyName');
    }

    /**
     * Call a relation key accessor only when it does not require arguments.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     */
    private static function callWithoutArguments(Relation $relation, string $method): ?string
    {
        if (! method_exists($relation, $method)) {
            return null;
        }

        try {
            $reflection = new \ReflectionMethod($relation, $method);
        } catch (\ReflectionException) {
            return null;
        }

        if (! $reflection->isPublic() || $reflection->getNumberOfRequiredParameters() > 0) {
            return null;
        }

        $value = $relation->{$method}();

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param  Relation<Model, Model, mixed>  $relation
     */
    private static function requiresArguments(Relation $relation, string $method): bool
    {
        if (! method_exists($relation, $method)) {
            return false;
        }

        try {
            $reflection = new \ReflectionMethod($relation, $method);
        } catch (\ReflectionException) {
            return false;
        }

        return $reflection->isPublic() && $reflection->getNumberOfRequiredParameters() > 0;
    }

    /**
     * Qualify the columns of a BelongsToThrough relation, whose query joins the intermediate tables.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     * @param  array<string>  $columns
     * @return array<string>
     */
    private static function qualifyColumns(Relation $relation, array $columns): array
    {
        if (! self::isBelongsToThrough($relation)) {
            return $columns;
        }

        return array_map(
            static fn (string $column): string => str_contains($column, '.') || stripos($column, ' as ') !== false
                ? $column
                : $relation->getRelated()->qualifyColumn($column),
            $columns
        );
    }
}
