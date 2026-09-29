<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\LazyCollection;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Support\EloquentShapeSteps;
use Jackardios\QueryWizard\Support\RelationResolver;

/**
 * The validated includes, sparse fieldsets and appends of one build, for any
 * Eloquent query that loads the resource's models.
 *
 * It holds no reference to the wizard and reads no configuration, so builders
 * and callbacks that capture it stay stable when the wizard is cloned or
 * reconfigured. Get it from BaseQueryWizard::resolveEloquentShape().
 *
 * @api
 */
final class EloquentShape
{
    /**
     * @param  array<IncludeInterface>  $includes  In the order to apply them
     * @param  array<string, array<string>>  $relationFieldsByPath  Fieldsets the eager loads may be narrowed to
     * @param  array<string>|null  $rootFields  Null when the root select is not narrowed
     * @param  array<string>  $runtimeOnlyRootFields
     * @param  array<string>|null  $rootVisibleFields
     * @param  array<string>  $requiredRootColumns
     * @param  array{appends: array<string>, relations: array<string, mixed>}  $appendTree
     * @param  array{fields: array<string>, relations: array<string, mixed>}  $relationFieldTree
     *
     * @internal Created by BaseQueryWizard::resolveEloquentShape().
     */
    public function __construct(
        private readonly array $includes,
        private readonly array $relationFieldsByPath,
        private readonly ?array $rootFields,
        private readonly array $runtimeOnlyRootFields,
        private readonly ?array $rootVisibleFields,
        private readonly array $requiredRootColumns,
        private readonly array $appendTree,
        private readonly array $relationFieldTree,
    ) {}

    /**
     * Eager load the includes and narrow the selects.
     *
     * Relation fieldsets narrow the eager-load queries, after any constraint
     * already registered for the relation. The root select keeps aggregates,
     * other selected expressions, the keys of every registered eager load and
     * the required root columns. Call it once per query, after every other
     * eager load and select the query should keep.
     *
     * @template TQuery of Builder<covariant Model>|Relation<covariant Model, covariant Model, *>
     *
     * @param  TQuery  $query
     * @return TQuery|Builder<Model>|Relation<Model, Model, mixed> The query to run: a callback include may return another instance
     */
    public function applyTo(Builder|Relation $query): Builder|Relation
    {
        /** @var Builder<Model>|Relation<Model, Model, mixed> $subject */
        $subject = $query;
        $query = EloquentShapeSteps::applyIncludes($subject, $this->includes, $this->relationFieldsByPath);

        if ($this->rootFields !== null) {
            $rootAppendsRequested = $this->appendTree['appends'] !== [];

            EloquentShapeSteps::applyRootSelect(
                $query,
                $this->rootFields,
                $this->runtimeOnlyRootFields,
                $this->requiredRootColumns,
                new RelationResolver($query->getModel()),
                static fn (): bool => $rootAppendsRequested
            );
        }

        return $query;
    }

    /**
     * Apply the root and relation fieldsets and the appends to loaded models.
     *
     * A lazy collection is not read: a new one is returned that post-processes
     * each model as it is read.
     *
     * @template TResults of Model|\Traversable<mixed>|array<mixed>
     *
     * @param  TResults  $results  A model, a collection, a lazy collection, a paginator or an array of models
     * @return (TResults is LazyCollection<array-key, mixed> ? LazyCollection<array-key, mixed> : TResults) The same results, or a new lazy collection for a lazy collection
     *
     * @throws \InvalidArgumentException For a generator, which post-processing would use up
     */
    public function postProcess(mixed $results): mixed
    {
        if ($results instanceof LazyCollection) {
            return $results->tapEach(fn (mixed $item) => EloquentShapeSteps::postProcess(
                $item,
                $this->rootVisibleFields,
                $this->appendTree,
                $this->relationFieldTree
            ));
        }

        EloquentShapeSteps::assertNotGenerator($results);
        EloquentShapeSteps::postProcess($results, $this->rootVisibleFields, $this->appendTree, $this->relationFieldTree);

        return $results;
    }
}
