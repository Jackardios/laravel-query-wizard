<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\BaseQueryWizard;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\EagerLoadsRelation;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Eloquent\Sorts\FieldSort;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;
use Jackardios\QueryWizard\Support\EloquentShapeSteps;
use Jackardios\QueryWizard\Support\EloquentSubject;
use Jackardios\QueryWizard\Support\RelationResolver;
use Jackardios\QueryWizard\Support\SafeRelationSelect;

/**
 * Query wizard for Eloquent Builder queries.
 *
 * Handles list queries with filters, sorts, includes, fields, and appends.
 *
 * @mixin Builder<Model>
 *
 * @extends BaseQueryWizard<Builder<Model>|Relation<Model, Model, mixed>>
 *
 * @phpstan-consistent-constructor
 */
class EloquentQueryWizard extends BaseQueryWizard
{
    private const CURSOR_EAGER_LOAD_CHUNK_SIZE = 1000;

    /**
     * Builder methods that return models of the query, post-processed when called through the wizard.
     */
    private const POST_PROCESSED_PROXY_METHODS = [
        'find' => true,
        'findmany' => true,
        'findorfail' => true,
        'findor' => true,
        'findsole' => true,
        'sole' => true,
        'firstwhere' => true,
        'firstor' => true,
    ];

    /**
     * Finders whose closure argument is a fallback rather than a constraint.
     */
    private const FALLBACK_PROXY_METHODS = [
        'findor' => true,
        'firstor' => true,
    ];

    /** @var Builder<Model>|Relation<Model, Model, mixed> */
    protected mixed $subject;

    private bool $proxyModified = false;

    private bool $subjectEscaped = false;

    private bool $failedWithEscapedSubject = false;

    private EloquentBuildState $state;

    /**
     * @param  Builder<covariant Model>|Relation<covariant Model, covariant Model, *>  $subject
     */
    public function __construct(
        Builder|Relation $subject,
        ?QueryParametersManager $parameters = null,
        ?QueryWizardConfig $config = null,
        ?ResourceSchemaInterface $schema = null
    ) {
        /** @var Builder<Model>|Relation<Model, Model, mixed> $subject */
        parent::__construct($subject, $parameters, $config, $schema);
        $this->state = new EloquentBuildState;
    }

    /**
     * Create a wizard for a model class, query builder, or relation.
     *
     * A model instance is not accepted: its query would select every row, not
     * the model. Use `ModelQueryWizard` to process a loaded model.
     *
     * The request-scoped parameters manager is used; a wizard reading another
     * one is created with the constructor.
     *
     * @param  class-string<Model>|Builder<covariant Model>|Relation<covariant Model, covariant Model, *>  $subject
     *
     * @throws \InvalidArgumentException When more than the subject is passed
     */
    public static function for(string|Builder|Relation $subject): static
    {
        if (func_num_args() > 1) {
            throw new \InvalidArgumentException(
                static::class.'::for() takes the subject only; pass a QueryParametersManager to the constructor.'
            );
        }

        if (is_string($subject)) {
            /** @var class-string<Model> $className */
            $className = $subject;
            $subject = $className::query();
        }

        return new static($subject);
    }

    /**
     * Create a wizard from a resource schema.
     *
     * @param  class-string<ResourceSchemaInterface>|ResourceSchemaInterface  $schema
     */
    public static function forSchema(string|ResourceSchemaInterface $schema): static
    {
        $schema = is_string($schema) ? app($schema) : $schema;

        /** @var class-string<Model> $modelClass */
        $modelClass = $schema->model();

        return new static($modelClass::query(), null, null, $schema);
    }

    /**
     * Build and execute query, returning all results.
     *
     * @param  array<int, string>|string  $columns
     * @return Collection<int, Model>
     */
    public function get(array|string $columns = ['*']): Collection
    {
        return $this->execute(fn () => $this->subject->get($columns));
    }

    /**
     * Build and execute query, returning first result.
     *
     * @param  array<int, string>|string  $columns
     */
    public function first(array|string $columns = ['*']): ?Model
    {
        return $this->execute(fn () => $this->subject->first($columns));
    }

    /**
     * Build and execute query, returning first result or throwing exception.
     *
     * @param  array<int, string>|string  $columns
     *
     * @throws ModelNotFoundException<Model>
     */
    public function firstOrFail(array|string $columns = ['*']): Model
    {
        return $this->execute(fn () => $this->subject->firstOrFail($columns));
    }

    /**
     * Build and execute query with pagination.
     *
     * @param  array<int, string>  $columns
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(
        ?int $perPage = null,
        array $columns = ['*'],
        string $pageName = 'page',
        ?int $page = null,
        \Closure|int|null $total = null
    ): LengthAwarePaginator {
        return $this->execute(fn () => $this->subject->paginate($perPage, $columns, $pageName, $page, $total));
    }

    /**
     * Build and execute query with simple pagination.
     *
     * @param  array<int, string>  $columns
     * @return Paginator<int, Model>
     */
    public function simplePaginate(
        ?int $perPage = null,
        array $columns = ['*'],
        string $pageName = 'page',
        ?int $page = null
    ): Paginator {
        return $this->execute(fn () => $this->subject->simplePaginate($perPage, $columns, $pageName, $page));
    }

    /**
     * Build and execute query with cursor pagination.
     *
     * @param  array<int, string>  $columns
     * @return CursorPaginator<int, Model>
     */
    public function cursorPaginate(
        ?int $perPage = null,
        array $columns = ['*'],
        string $cursorName = 'cursor',
        Cursor|string|null $cursor = null
    ): CursorPaginator {
        return $this->execute(function () use ($perPage, $columns, $cursorName, $cursor) {
            $this->ensureCursorOrderColumnsSelected();

            return $this->subject->cursorPaginate($perPage, $columns, $cursorName, $cursor);
        });
    }

    /**
     * Build and execute query in chunks with automatic post-processing.
     *
     * @param  positive-int  $count
     * @param  callable(Collection<int, Model>, int): mixed  $callback
     */
    public function chunk(int $count, callable $callback): bool
    {
        $this->buildSubject();

        return $this->subject->chunk($count, $this->postProcessingChunks($callback));
    }

    /**
     * Build and execute query with lazy collection with automatic post-processing.
     *
     * @return LazyCollection<int, Model>
     */
    public function lazy(int $chunkSize = 1000): LazyCollection
    {
        $this->buildSubject();

        return $this->postProcessingLazily($this->subject->lazy($chunkSize));
    }

    /**
     * Build and execute query with cursor (memory-efficient) with automatic post-processing.
     *
     * Laravel's cursor() skips eager loading, so included relationships are
     * loaded for every CURSOR_EAGER_LOAD_CHUNK_SIZE models, which are kept in
     * memory until then.
     *
     * @return LazyCollection<int, Model>
     */
    public function cursor(): LazyCollection
    {
        $this->buildSubject();

        $builder = EloquentSubject::builder($this->subject);
        $models = $this->subject->cursor();

        if ($builder->getEagerLoads() !== []) {
            $models = $models
                ->chunk(self::CURSOR_EAGER_LOAD_CHUNK_SIZE)
                ->flatMap(fn (LazyCollection $chunk): array => $builder->eagerLoadRelations($chunk->values()->all()));
        }

        return $this->postProcessingLazily($models);
    }

    /**
     * Build and execute query in chunks by ID with automatic post-processing.
     *
     * @param  positive-int  $count
     * @param  callable(Collection<int, Model>, int): mixed  $callback
     */
    public function chunkById(int $count, callable $callback, ?string $column = null, ?string $alias = null): bool
    {
        $this->buildSubjectSelecting($column, $alias);

        return $this->subject->chunkById($count, $this->postProcessingChunks($callback), $column, $alias);
    }

    /**
     * Build and execute query in descending chunks by ID with automatic post-processing.
     *
     * @param  positive-int  $count
     * @param  callable(Collection<int, Model>, int): mixed  $callback
     */
    public function chunkByIdDesc(int $count, callable $callback, ?string $column = null, ?string $alias = null): bool
    {
        $this->buildSubjectSelecting($column, $alias);

        return $this->subject->chunkByIdDesc($count, $this->postProcessingChunks($callback), $column, $alias);
    }

    /**
     * Build and execute query model by model, in chunks by ID, with automatic post-processing.
     *
     * @param  callable(Model, int): mixed  $callback
     */
    public function eachById(callable $callback, int $count = 1000, ?string $column = null, ?string $alias = null): bool
    {
        $this->buildSubjectSelecting($column, $alias);

        return $this->subject->eachById($this->postProcessingEach($callback), $count, $column, $alias);
    }

    /**
     * Build and execute query model by model, in chunks, with automatic post-processing.
     *
     * @param  callable(Model, int): mixed  $callback
     */
    public function each(callable $callback, int $count = 1000): bool
    {
        $this->buildSubject();

        return $this->subject->each($this->postProcessingEach($callback), $count);
    }

    /**
     * Build and execute query in chunks, mapping every post-processed model.
     *
     * @template TReturn
     *
     * @param  callable(Model): TReturn  $callback
     * @return Collection<int, TReturn>
     */
    public function chunkMap(callable $callback, int $count = 1000): Collection
    {
        $this->buildSubject();

        return $this->subject->chunkMap(function (Model $model) use ($callback) {
            $this->applyPostProcessingToResults($model);

            return $callback($model);
        }, $count);
    }

    /**
     * Build and execute query lazily in chunks by ID with automatic post-processing.
     *
     * @return LazyCollection<int, Model>
     */
    public function lazyById(int $chunkSize = 1000, ?string $column = null, ?string $alias = null): LazyCollection
    {
        $this->buildSubjectSelecting($column, $alias);

        return $this->postProcessingLazily($this->subject->lazyById($chunkSize, $column, $alias));
    }

    /**
     * Build and execute query lazily in descending chunks by ID with automatic post-processing.
     *
     * @return LazyCollection<int, Model>
     */
    public function lazyByIdDesc(int $chunkSize = 1000, ?string $column = null, ?string $alias = null): LazyCollection
    {
        $this->buildSubjectSelecting($column, $alias);

        return $this->postProcessingLazily($this->subject->lazyByIdDesc($chunkSize, $column, $alias));
    }

    /**
     * Build the subject and select the column that chunks by ID are keyed on.
     */
    private function buildSubjectSelecting(?string $column, ?string $alias): void
    {
        $this->buildSubject();
        $this->ensureColumnSelected($column ?? $this->subject->getModel()->getKeyName(), $alias);
    }

    /**
     * @param  LazyCollection<int, Model>  $models
     * @return LazyCollection<int, Model>
     */
    private function postProcessingLazily(LazyCollection $models): LazyCollection
    {
        return $models->tapEach(fn (Model $model) => $this->applyPostProcessingToResults($model));
    }

    /**
     * @param  callable(Model, int): mixed  $callback
     * @return \Closure(Model, int): mixed
     */
    private function postProcessingEach(callable $callback): \Closure
    {
        return function (Model $model, int $key) use ($callback): mixed {
            $this->applyPostProcessingToResults($model);

            return $callback($model, $key);
        };
    }

    /**
     * Post-process every chunk before the callback, passing the page number on.
     *
     * @param  callable(Collection<int, Model>, int): mixed  $callback
     * @return \Closure(Collection<int, Model>, int): mixed
     */
    private function postProcessingChunks(callable $callback): \Closure
    {
        return function (Collection $models, int $page) use ($callback): mixed {
            $this->applyPostProcessingToResults($models);

            return $callback($models, $page);
        };
    }

    /**
     * Apply full post-processing (root fields, relation fields, appends) to externally fetched results.
     *
     * Use this when fetching results via `toQuery()` and methods that bypass the wizard
     * (e.g., `$wizard->toQuery()->chunk()`). For direct wizard methods like `$wizard->chunk()`,
     * `$wizard->lazy()`, etc., post-processing is applied automatically.
     *
     * A lazy collection is not read: a new one is returned that post-processes
     * each model as it is read.
     *
     * @template T of Model|\Traversable<mixed>|array<mixed>
     *
     * @param  T  $results  Single model, collection, lazy collection, or iterable of models
     * @return (T is LazyCollection<array-key, mixed> ? LazyCollection<array-key, mixed> : T) The same results with post-processing applied, or a new lazy collection for a lazy collection
     *
     * @throws \InvalidArgumentException For a generator, which post-processing would use up
     */
    public function applyPostProcessingTo(mixed $results): mixed
    {
        $this->buildSubject();

        if ($results instanceof LazyCollection) {
            return $results->tapEach(fn (mixed $item) => $this->applyPostProcessingToResults($item));
        }

        EloquentShapeSteps::assertNotGenerator($results);
        $this->applyPostProcessingToResults($results);

        return $results;
    }

    /**
     * Prepare the relation sparse-fields and append trees as part of the build.
     *
     * Kept inside build() rather than deferred to post-processing so that an
     * invalid ?fields or ?append request fails before the query is executed,
     * also for toQuery() and builder calls that skip post-processing. An
     * override should call parent::finalizeBuild(); without it the trees are
     * prepared when results are post-processed, after the query ran.
     */
    protected function finalizeBuild(): void
    {
        $this->prepareRelationFieldData();
        $this->prepareAppendTree();
    }

    /**
     * Build and return the live query builder, like toQuery().
     *
     * The caller holds the builder afterwards, so the wizard can't be
     * reconfigured any more.
     *
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function build(): Builder|Relation
    {
        return $this->toQuery();
    }

    /**
     * Build and return the query builder (without executing).
     *
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function toQuery(): Builder|Relation
    {
        $this->buildSubject();
        $this->subjectEscaped = true;

        return $this->subject;
    }

    /**
     * Build for the wizard's own use, without handing the builder out.
     *
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    private function buildSubject(): Builder|Relation
    {
        if ($this->failedWithEscapedSubject) {
            throw new \LogicException(
                'A build did not finish after the underlying builder was handed out, so that builder holds part of it. '
                .'Create a new wizard instead of building this one again.'
            );
        }

        if ($this->built && ($this->proxyModified || $this->subjectEscaped) && $this->builtScopeSignature !== $this->resolveBuildScopeSignature()) {
            throw new \LogicException(
                'The request parameters changed after the underlying builder was handed out or changed through the wizard. '
                .'Create a new wizard for each request.'
            );
        }

        return parent::build();
    }

    /**
     * Get the underlying query builder.
     *
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function getSubject(): Builder|Relation
    {
        $this->subjectEscaped = true;

        return $this->subject;
    }

    protected function invalidateBuild(): void
    {
        $this->assertNotReadingSchema();

        if ($this->proxyModified) {
            throw new \LogicException(
                'Cannot modify query wizard configuration after calling query builder methods (e.g. where(), orderBy()). '
                .'Call all configuration methods (allowedFilters, allowedSorts, etc.) before query builder methods.'
            );
        }

        if ($this->subjectEscaped) {
            throw new \LogicException(
                'Cannot modify query wizard configuration after retrieving the underlying builder via build(), toQuery(), getSubject() or getQuery(). '
                .'Those methods expose the live builder, so call all configuration methods before builder access.'
            );
        }

        $this->state = new EloquentBuildState;
        parent::invalidateBuild();
    }

    /**
     * A subject already handed out by toQuery() or getSubject() is kept: the
     * caller holds that instance, so swapping it would detach them from it.
     * It holds part of the failed build, so the wizard refuses to build again.
     */
    protected function rollbackFailedBuild(): void
    {
        $subject = $this->subject;

        $this->state = new EloquentBuildState;
        parent::rollbackFailedBuild();

        if ($this->subjectEscaped) {
            $this->subject = $subject;
            $this->failedWithEscapedSubject = true;
        }
    }

    /**
     * The clone keeps the taint flags, so it refuses reconfiguration like its
     * source: reconfiguring rebuilds from the original subject and would drop
     * the constraints added through the proxy.
     *
     * The derived post-processing state (append tree, relation field tree, root
     * field masks, runtime attribute maps) is copied, not cleared. It describes
     * the subject this clone carries over, and only build() can rebuild it - so
     * clearing it here would leave a cloned built wizard unable to ever apply
     * its sparse fieldsets or appends again. The copy keeps the clone's later
     * changes (e.g. chunkById() hiding its key column) out of the source.
     */
    public function __clone(): void
    {
        parent::__clone();
        $this->state = clone $this->state;
    }

    protected function resourceModel(): Model
    {
        return EloquentSubject::builder($this->subject)->getModel();
    }

    protected function relationResolverFor(Model $rootModel): RelationResolver
    {
        if ($this->state->relationResolver?->getRootModel() === $rootModel) {
            return $this->state->relationResolver;
        }

        if ($rootModel !== $this->subject->getModel()) {
            return new RelationResolver($rootModel);
        }

        return $this->state->relationResolver = new RelationResolver($rootModel);
    }

    protected function normalizeStringToFilter(string $name): FilterInterface
    {
        return ExactFilter::make($name);
    }

    protected function normalizeStringToSort(string $name): SortInterface
    {
        $property = ltrim($name, '-');

        return FieldSort::make($property);
    }

    protected function normalizeStringToInclude(string $name): IncludeInterface
    {
        $config = $this->getConfig();

        return RelationshipInclude::fromString($name, $config->getCountSuffix(), $config->getExistsSuffix());
    }

    protected function applyFields(array $fields): void
    {
        $runtimeAttributes = $this->state->runtimeRootAttributeNamesByField;
        $this->state->rootVisibleFields = $this->visibleRootFields($fields, $runtimeAttributes);
        $this->state->safeRootHiddenFields = [];

        EloquentShapeSteps::applyRootSelect(
            $this->subject,
            $fields,
            $this->runtimeOnlyRootFields($fields, $runtimeAttributes),
            [],
            $this->relationResolverFor($this->subject->getModel()),
            function (): bool {
                $this->prepareAppendTree();

                return ! empty($this->state->appendTree['appends']);
            }
        );
    }

    /**
     * @param  array<int, string>  $validRequestedIncludes
     * @param  array<string, IncludeInterface>  $includesIndex
     */
    protected function applyValidatedIncludes(array $validRequestedIncludes, array $includesIndex): void
    {
        $includes = [];
        $relationshipPaths = [];

        foreach ($validRequestedIncludes as $includeName) {
            $include = $includes[] = $includesIndex[$includeName];

            if ($include instanceof EagerLoadsRelation) {
                $relationshipPaths[] = $include->getRelation();
            }
        }

        $relationFieldsByPath = $this->safeRelationFieldsByPath($relationshipPaths);
        $this->registerRuntimeAttributes($validRequestedIncludes, $includesIndex);

        $this->subject = EloquentShapeSteps::applyIncludes($this->subject, $includes, $relationFieldsByPath);
    }

    public function getResourceKey(): string
    {
        return $this->resolveDefaultResourceKey($this->subject->getModel());
    }

    /**
     * @return array<string, array<string>>
     */
    protected function validatedRelationFieldMap(): array
    {
        return $this->state->relationFieldMap ??= $this->buildValidatedRelationFieldMap();
    }

    /**
     * Build relation sparse-fields map/tree once per built wizard.
     */
    private function prepareRelationFieldData(): void
    {
        if ($this->state->relationFieldTreePrepared) {
            return;
        }

        $this->state->relationFieldTree = $this->withRuntimeAttributesInFieldTree(
            $this->buildRelationFieldTree($this->validatedRelationFieldMap()),
            $this->state->runtimeRelationAttributes
        );
        $this->state->relationFieldTreePrepared = true;
    }

    private function prepareAppendTree(): void
    {
        if ($this->state->appendTreePrepared) {
            return;
        }

        $this->state->appendTree = $this->getValidRequestedAppendsTree();
        $this->state->appendTreePrepared = true;
    }

    /**
     * Apply the root mask, relation sparse fieldsets and appends.
     */
    private function applyPostProcessingToResults(mixed $results): void
    {
        $safeRootHiddenFields = $this->state->safeRootHiddenFields;

        if ($safeRootHiddenFields !== []) {
            if ($results instanceof Model) {
                $results->makeHidden($safeRootHiddenFields);
            } elseif ($results instanceof \Traversable || is_array($results)) {
                foreach ($results as $item) {
                    if ($item instanceof Model) {
                        $item->makeHidden($safeRootHiddenFields);
                    }
                }
            }
        }

        $this->prepareRelationFieldData();
        $this->prepareAppendTree();

        EloquentShapeSteps::postProcess(
            $results,
            $this->state->rootVisibleFields,
            $this->state->appendTree,
            $this->state->relationFieldTree
        );
    }

    /**
     * @param  array<int, string>  $includeNames
     * @param  array<string, IncludeInterface>  $includesIndex
     */
    private function registerRuntimeAttributes(array $includeNames, array $includesIndex): void
    {
        $attributesByOwner = $this->resolveRuntimeAttributesByOwner($includeNames, $includesIndex);

        $this->state->runtimeRootAttributeNamesByField = $attributesByOwner[''] ?? [];

        unset($attributesByOwner['']);
        $this->state->runtimeRelationAttributes = $attributesByOwner;
    }

    /**
     * Cursor pagination reads the value of every order column from the last
     * item (Laravel orders by the key when there is no order), so a narrowed
     * select has to include them.
     */
    private function ensureCursorOrderColumnsSelected(): void
    {
        $query = EloquentSubject::baseQuery($this->subject);

        if (! empty($query->unionOrders)) {
            return;
        }

        if (empty($query->orders)) {
            $this->ensureColumnSelected($this->subject->getModel()->getKeyName());

            return;
        }

        $table = $this->subject->getModel()->getTable();

        foreach ($query->orders as $order) {
            $column = $order['column'] ?? null;

            if (! isset($order['direction']) || ! is_string($column) || str_contains($column, '(')) {
                continue;
            }

            if (str_contains($column, '.') && Str::beforeLast($column, '.') !== $table) {
                continue;
            }

            $columnName = Str::afterLast($column, '.');

            if (! EloquentSubject::hasSelectAlias($this->subject, $columnName)) {
                $this->ensureColumnSelected($columnName);
            }
        }
    }

    /**
     * Select the root columns that eager loads added through the wizard match by.
     *
     * The build narrowed the root select to the fieldset knowing only the eager
     * loads registered until then, so a later `->with()` would find no parent
     * keys and load nothing. When the key columns of an eager load are unknown,
     * every root column is selected again; the root fieldset still hides the
     * rest. A developer's own select gets the same columns while a root fieldset
     * applies; without one the select is not touched. Eager loads added to the
     * builder that toQuery() or build() hands out do not pass through here.
     */
    private function ensureEagerLoadKeysSelected(): void
    {
        $selectedColumns = EloquentSubject::baseQuery($this->subject)->columns;

        if ($this->state->rootVisibleFields === null || $selectedColumns === null || $this->selectsAllColumns($selectedColumns)) {
            return;
        }

        $eagerLoadNames = SafeRelationSelect::topLevelEagerLoadNames(EloquentSubject::builder($this->subject)->getEagerLoads());
        $columns = SafeRelationSelect::parentColumnsForEagerLoads(
            $this->relationResolverFor($this->subject->getModel()),
            $eagerLoadNames
        );

        if ($columns === null) {
            $this->subject->addSelect($this->subject->qualifyColumn('*'));

            return;
        }

        foreach ($columns as $column) {
            $this->ensureColumnSelected($column);
        }
    }

    /**
     * Select a root column the execution needs, hidden from the output.
     */
    private function ensureColumnSelected(string $columnName, ?string $alias = null): void
    {
        $selectedColumns = EloquentSubject::baseQuery($this->subject)->columns;

        if ($selectedColumns === null || $this->selectsAllColumns($selectedColumns)) {
            return;
        }

        $qualifiedColumn = $this->subject->qualifyColumn($columnName);

        if ($this->queryAlreadySelectsColumn($selectedColumns, $columnName, $qualifiedColumn, $alias)) {
            return;
        }

        if ($alias !== null) {
            $this->subject->addSelect("{$qualifiedColumn} as {$alias}");
            $this->state->safeRootHiddenFields[] = $alias;

            return;
        }

        $this->subject->addSelect($qualifiedColumn);
        $this->state->safeRootHiddenFields[] = $columnName;
    }

    /**
     * @param  array<int, mixed>  $selectedColumns
     */
    private function selectsAllColumns(array $selectedColumns): bool
    {
        foreach ($selectedColumns as $selectedColumn) {
            if (is_string($selectedColumn) && ($selectedColumn === '*' || $selectedColumn === $this->subject->qualifyColumn('*'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $selectedColumns
     */
    private function queryAlreadySelectsColumn(
        array $selectedColumns,
        string $columnName,
        string $qualifiedColumn,
        ?string $alias
    ): bool {
        foreach ($selectedColumns as $selectedColumn) {
            if (! is_string($selectedColumn)) {
                continue;
            }

            $normalizedColumn = strtolower(trim($selectedColumn));
            $normalizedQualified = strtolower($qualifiedColumn);
            $normalizedName = strtolower($columnName);

            if (
                $normalizedColumn === $normalizedQualified
                || $normalizedColumn === $normalizedName
                || str_ends_with($normalizedColumn, '.'.$normalizedName)
            ) {
                return true;
            }

            if ($alias !== null && preg_match('/\bas\s+("?'.preg_quote(strtolower($alias), '/').'"?)$/', $normalizedColumn) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build, run the executor and post-process what it returns.
     *
     * @template TResult of Collection<int, Model>|Model|Paginator<int, Model>|CursorPaginator<int, Model>|null
     *
     * @param  callable(): TResult  $executor
     * @return TResult
     */
    private function execute(callable $executor): mixed
    {
        $this->buildSubject();
        $result = $executor();

        if ($result !== null) {
            $this->applyPostProcessingToResults(
                $result instanceof Paginator || $result instanceof CursorPaginator ? $result->items() : $result
            );
        }

        return $result;
    }

    /**
     * Proxy method calls to the underlying query builder.
     *
     * A call that returns the subject itself returns the wizard; anything else,
     * including a different builder or relation, is returned as it is. Finders
     * (find(), sole(), firstWhere(), ...) return post-processed models, except
     * for the result of a findOr()/firstOr() fallback callback.
     *
     * A method the subject cannot handle throws before the request is read.
     *
     * @param  array<int, mixed>  $arguments
     *
     * @throws \BadMethodCallException When neither the wizard nor its subject has the method
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (! EloquentSubject::handles($this->subject, $name)) {
            throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $name));
        }

        $this->buildSubject();

        $postProcess = isset(self::POST_PROCESSED_PROXY_METHODS[strtolower($name)]);
        $usedFallback = false;

        if (isset(self::FALLBACK_PROXY_METHODS[strtolower($name)])) {
            foreach ($arguments as $index => $argument) {
                if ($argument instanceof \Closure) {
                    $arguments[$index] = static function (mixed ...$args) use ($argument, &$usedFallback): mixed {
                        $usedFallback = true;

                        return $argument(...$args);
                    };
                }
            }
        }

        $eagerLoadNames = array_keys(EloquentSubject::builder($this->subject)->getEagerLoads());
        $result = $this->subject->$name(...$arguments);

        if ($result === $this->subject) {
            $this->proxyModified = true;

            if (array_keys(EloquentSubject::builder($this->subject)->getEagerLoads()) !== $eagerLoadNames) {
                $this->ensureEagerLoadKeysSelected();
            }

            return $this;
        }

        if ($result === EloquentSubject::builder($this->subject) || $result === EloquentSubject::baseQuery($this->subject)) {
            $this->subjectEscaped = true;
        }

        if ($postProcess && ! $usedFallback && ($result instanceof Model || $result instanceof Collection)) {
            $this->applyPostProcessingToResults($result);
        }

        return $result;
    }
}
