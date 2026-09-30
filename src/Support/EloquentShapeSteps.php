<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Support;

use Closure;
use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use Jackardios\QueryWizard\Contracts\EagerLoadsRelation;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;

/**
 * The steps that shape an Eloquent query and its results: includes, the root
 * select and post-processing. EloquentQueryWizard and EloquentShape both run
 * them, so the two can't drift apart.
 *
 * @internal
 */
final class EloquentShapeSteps
{
    /**
     * Apply the includes, narrowing the eager loads of relationship includes to their fieldsets.
     *
     * An eager load registered before keeps its constraint; the fieldset runs after it.
     *
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     * @param  array<IncludeInterface>  $includes  In the order to apply them
     * @param  array<string, array<string>>  $relationFieldsByPath  Fieldsets the eager loads may be narrowed to
     * @return Builder<Model>|Relation<Model, Model, mixed> The subject, or the instance a callback include returned
     */
    public static function applyIncludes(Builder|Relation $subject, array $includes, array $relationFieldsByPath): Builder|Relation
    {
        foreach ($includes as $include) {
            $fields = $include instanceof EagerLoadsRelation
                ? ($relationFieldsByPath[$include->getRelation()] ?? null)
                : null;
            $select = $fields === null ? null : static function ($query) use ($fields): void {
                SafeRelationSelect::apply($query, $fields);
            };

            if ($include instanceof RelationshipInclude) {
                EagerLoads::merge($subject, $include->getRelation(), $select);

                continue;
            }

            $result = EagerLoads::preserving($subject, static fn ($subject): mixed => $include->apply($subject));

            if ($result instanceof Builder || $result instanceof Relation) {
                $subject = $result;
            }

            if ($select !== null) {
                EagerLoads::merge($subject, $include->getRelation(), $select);
            }
        }

        return $subject;
    }

    /**
     * Narrow the root select to the requested fields.
     *
     * The columns the registered eager loads match by and the required columns
     * are selected too, and aggregates and other expressions already selected
     * are kept. When root appends may be serialized the select stays whole,
     * because accessors can read any attribute; so does it when the key
     * columns of an eager load are unknown.
     *
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     * @param  array<string>  $fields  Validated root fields
     * @param  array<string>  $runtimeOnlyFields  Requested fields that name attributes the includes add
     * @param  array<string>  $requiredColumns  Columns to select under any fieldset
     * @param  RelationResolver  $relations  Relations of the subject's model
     * @param  Closure(): bool  $rootAppendsRequested  Asked only when the answer matters
     */
    public static function applyRootSelect(
        Builder|Relation $subject,
        array $fields,
        array $runtimeOnlyFields,
        array $requiredColumns,
        RelationResolver $relations,
        Closure $rootAppendsRequested
    ): void {
        $model = $subject->getModel();

        if ($fields !== [] && $fields !== ['*'] && (! empty($model->getAppends()) || $rootAppendsRequested())) {
            return;
        }

        $eagerLoadNames = SafeRelationSelect::topLevelEagerLoadNames(EloquentSubject::builder($subject)->getEagerLoads());
        $eagerLoadColumns = $eagerLoadNames === [] ? [] : SafeRelationSelect::parentColumnsForEagerLoads($relations, $eagerLoadNames);

        if ($eagerLoadColumns === null) {
            return;
        }

        $query = EloquentSubject::baseQuery($subject);
        $preservedExpressions = self::preservedSelectExpressions($subject);

        if (! in_array('*', $fields, true)) {
            SafeRelationSelect::appendColumns($fields, $eagerLoadColumns);
            SafeRelationSelect::appendColumns($fields, $requiredColumns, true);

            $excluded = array_fill_keys([...$runtimeOnlyFields, ...self::selectAliases($subject, $preservedExpressions)], true);
            $fields = array_values(array_filter($fields, static fn (string $field): bool => ! isset($excluded[$field])));
        }

        if ($fields === [] || $fields === ['*']) {
            return;
        }

        // select() drops the select bindings too. Only preserved expressions
        // can carry placeholders and they are re-added in their original
        // order, so the original bindings line up with them again.
        $selectBindings = $query->bindings['select'];

        $subject->select(array_map(static fn (string $field): string => $model->qualifyColumn($field), $fields));

        foreach ($preservedExpressions as $expression) {
            $subject->addSelect($expression);
        }

        $query->setBindings($selectBindings, 'select');
    }

    /**
     * @throws InvalidArgumentException For a generator: post-processing would use it up
     */
    public static function assertNotGenerator(mixed $results): void
    {
        if ($results instanceof \Generator) {
            throw new InvalidArgumentException(
                'A generator can be read only once, so post-processing it would leave nothing to return. '
                .'Post-process each model it yields, or pass a LazyCollection.'
            );
        }
    }

    /**
     * Hide the root attributes outside the fieldset, then apply relation fieldsets and appends.
     *
     * @param  array<string>|null  $rootVisibleFields  Null when every root attribute stays visible
     * @param  array{appends: array<string>, relations: array<string, mixed>}  $appendTree
     * @param  array{fields: array<string>, relations: array<string, mixed>}  $relationFieldTree
     */
    public static function postProcess(mixed $results, ?array $rootVisibleFields, array $appendTree, array $relationFieldTree): void
    {
        if (! $results instanceof Model && ! $results instanceof \Traversable && ! is_array($results)) {
            return;
        }

        if ($rootVisibleFields !== null) {
            if ($results instanceof Model) {
                ModelPostProcessor::hideAttributesExcept($results, $rootVisibleFields);
            } else {
                foreach ($results as $item) {
                    if ($item instanceof Model) {
                        ModelPostProcessor::hideAttributesExcept($item, $rootVisibleFields);
                    }
                }
            }
        }

        ModelPostProcessor::applyToRelations($results, $appendTree, $relationFieldTree);
    }

    /**
     * Selected expressions, aliased columns and function calls, which a narrowed select keeps.
     *
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     * @return array<int, ExpressionContract|string>
     */
    private static function preservedSelectExpressions(Builder|Relation $subject): array
    {
        $preserved = [];

        foreach (EloquentSubject::baseQuery($subject)->columns ?? [] as $column) {
            if (
                $column instanceof Expression
                || (is_string($column) && (self::selectedColumnAlias($subject, $column) !== null || str_contains($column, '(')))
            ) {
                $preserved[] = $column;
            }
        }

        return $preserved;
    }

    /**
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     * @param  array<int, ExpressionContract|string>  $columns
     * @return array<string>
     */
    private static function selectAliases(Builder|Relation $subject, array $columns): array
    {
        $aliases = [];

        foreach ($columns as $column) {
            $alias = self::selectedColumnAlias($subject, $column);

            if ($alias !== null) {
                $aliases[] = $alias;
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     */
    private static function selectedColumnAlias(Builder|Relation $subject, mixed $column): ?string
    {
        if ($column instanceof Expression) {
            $column = $column->getValue(EloquentSubject::baseQuery($subject)->getGrammar());
        }

        if (! is_string($column) || preg_match('/\bas\s+[`"\\[]?([a-zA-Z0-9_]+)[`"\\]]?\s*$/i', $column, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
