<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent;

use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\BaseQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesRelationPostProcessing;
use Jackardios\QueryWizard\Concerns\HandlesSafeRelationSelect;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Eloquent\Sorts\FieldSort;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;
use Jackardios\QueryWizard\Support\EagerLoads;
use Jackardios\QueryWizard\Support\EloquentSubject;

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
    use HandlesRelationPostProcessing;
    use HandlesSafeRelationSelect;

    private const CURSOR_EAGER_LOAD_CHUNK_SIZE = 1000;

    /** @var Builder<Model>|Relation<Model, Model, mixed> */
    protected mixed $subject;

    private bool $proxyModified = false;

    private bool $subjectEscaped = false;

    private EloquentBuildState $state;

    /**
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $subject
     */
    public function __construct(
        Builder|Relation $subject,
        ?QueryParametersManager $parameters = null,
        ?QueryWizardConfig $config = null,
        ?ResourceSchemaInterface $schema = null
    ) {
        $this->subject = $subject;
        $this->originalSubject = clone $subject;
        $this->resolveParametersFromContainer = $parameters === null;
        $this->parameters = $parameters ?? app(QueryParametersManager::class);
        $this->config = $config ?? app(QueryWizardConfig::class);
        $this->schema = $schema;
        $this->state = new EloquentBuildState;
    }

    /**
     * Create a wizard for a model, query builder, or relation.
     *
     * @param  class-string<Model>|Builder<Model>|Relation<Model, Model, mixed>|Model  $subject
     */
    public static function for(string|Builder|Relation|Model $subject): static
    {
        if (is_string($subject)) {
            /** @var class-string<Model> $className */
            $className = $subject;
            $subject = $className::query();
        } elseif ($subject instanceof Model) {
            $subject = $subject->newQuery();
        }

        /** @var Builder<Model>|Relation<Model, Model, mixed> $subject */
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
        return $this->executeCollectionQuery(fn () => $this->subject->get($columns));
    }

    /**
     * Build and execute query, returning first result.
     *
     * @param  array<int, string>|string  $columns
     */
    public function first(array|string $columns = ['*']): ?Model
    {
        return $this->executeNullableModelQuery(fn () => $this->subject->first($columns));
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
        return $this->executeModelQuery(fn () => $this->subject->firstOrFail($columns));
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
        return $this->executePaginatorQuery(fn () => $this->subject->paginate($perPage, $columns, $pageName, $page, $total));
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
        return $this->executePaginatorQuery(fn () => $this->subject->simplePaginate($perPage, $columns, $pageName, $page));
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
        return $this->executePaginatorQuery(function () use ($perPage, $columns, $cursorName, $cursor) {
            $this->ensureCursorOrderColumnsSelected();

            return $this->subject->cursorPaginate($perPage, $columns, $cursorName, $cursor);
        });
    }

    /**
     * Build and execute query in chunks with automatic post-processing.
     *
     * @param  positive-int  $count
     * @param  callable(Collection<int, Model>): mixed  $callback
     */
    public function chunk(int $count, callable $callback): bool
    {
        $this->build();

        return $this->subject->chunk($count, function (Collection $models) use ($callback) {
            $this->applyPostProcessingToResults($models);

            return $callback($models);
        });
    }

    /**
     * Build and execute query with lazy collection with automatic post-processing.
     *
     * @return LazyCollection<int, Model>
     */
    public function lazy(int $chunkSize = 1000): LazyCollection
    {
        $this->build();

        return $this->subject->lazy($chunkSize)->map(function (Model $model) {
            $this->applyPostProcessingToResults($model);

            return $model;
        });
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
        $this->build();

        $builder = EloquentSubject::builder($this->subject);
        $models = $this->subject->cursor();

        if ($builder->getEagerLoads() !== []) {
            $models = $models
                ->chunk(self::CURSOR_EAGER_LOAD_CHUNK_SIZE)
                ->flatMap(fn (LazyCollection $chunk): array => $builder->eagerLoadRelations($chunk->values()->all()));
        }

        return $models->map(function (Model $model) {
            $this->applyPostProcessingToResults($model);

            return $model;
        });
    }

    /**
     * Build and execute query in chunks by ID with automatic post-processing.
     *
     * @param  positive-int  $count
     * @param  callable(Collection<int, Model>): mixed  $callback
     */
    public function chunkById(int $count, callable $callback, ?string $column = null, ?string $alias = null): bool
    {
        $this->build();
        $this->ensureColumnSelected($column ?? $this->subject->getModel()->getKeyName(), $alias);

        return $this->subject->chunkById($count, function (Collection $models) use ($callback) {
            $this->applyPostProcessingToResults($models);

            return $callback($models);
        }, $column, $alias);
    }

    /**
     * Apply full post-processing (root fields, relation fields, appends) to externally fetched results.
     *
     * Use this when fetching results via `toQuery()` and methods that bypass the wizard
     * (e.g., `$wizard->toQuery()->chunk()`). For direct wizard methods like `$wizard->chunk()`,
     * `$wizard->lazy()`, etc., post-processing is applied automatically.
     *
     * @template T of Model|\Traversable<mixed>|array<mixed>
     *
     * @param  T  $results  Single model, collection, or iterable of models
     * @return T The same results with post-processing applied
     */
    public function applyPostProcessingTo(mixed $results): mixed
    {
        $this->build();
        $this->applyPostProcessingToResults($results);

        return $results;
    }

    /**
     * Prepare the relation sparse-fields tree as part of the build.
     *
     * Kept inside build() rather than deferred to post-processing so that an
     * invalid ?fields request still fails before the query is executed.
     */
    protected function finalizeBuild(): void
    {
        $this->prepareRelationFieldData();
    }

    /**
     * Build and return the query builder (without executing).
     *
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function toQuery(): Builder|Relation
    {
        $this->build();
        $this->subjectEscaped = true;

        return $this->subject;
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
        if ($this->proxyModified) {
            throw new \LogicException(
                'Cannot modify query wizard configuration after calling query builder methods (e.g. where(), orderBy()). '
                .'Call all configuration methods (allowedFilters, allowedSorts, etc.) before query builder methods.'
            );
        }

        if ($this->subjectEscaped) {
            throw new \LogicException(
                'Cannot modify query wizard configuration after retrieving the underlying builder via toQuery() or getSubject(). '
                .'Those methods expose the live builder, so call all configuration methods before builder access.'
            );
        }

        $this->resetSafeRelationSelectState();
        $this->state = new EloquentBuildState;
        parent::invalidateBuild();
    }

    /**
     * Only the taint flags are reset: a fresh clone has not been handed out or
     * modified through the proxy yet.
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
        $this->proxyModified = false;
        $this->subjectEscaped = false;
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
        return RelationshipInclude::fromString($name, $this->config->getCountSuffix(), $this->config->getExistsSuffix());
    }

    protected function applyFields(array $fields): void
    {
        $requestedFields = $fields;
        $this->state->rootVisibleFields = $this->resolveVisibleRootFields($requestedFields);
        $this->state->safeRootHiddenFields = [];

        if ($this->shouldKeepFullRootSelectForAppends($requestedFields)) {
            return;
        }

        $eagerLoadColumns = $this->resolveRootEagerLoadColumns();

        if ($eagerLoadColumns === null) {
            return;
        }

        $preservedSelectExpressions = $this->collectPreservedSelectExpressions();
        $preservedSelectAliases = $this->collectPreservedSelectAliases($preservedSelectExpressions);

        $fields = $this->applySafeRootFieldRequirements($fields);

        if (! in_array('*', $fields, true)) {
            $this->appendColumns($fields, $eagerLoadColumns);
        }
        $fields = $this->excludeRuntimeOnlyRootFieldsFromSelect($fields, $preservedSelectAliases);

        if (! empty($fields) && $fields !== ['*']) {
            // select() drops the select bindings too. Only preserved expressions
            // can carry placeholders and they are re-added in their original
            // order, so the original bindings line up with them again.
            $selectBindings = EloquentSubject::baseQuery($this->subject)->bindings['select'];

            $qualifiedFields = $this->qualifyColumns($fields);
            $this->subject->select($qualifiedFields);
            $this->restorePreservedSelectExpressions($preservedSelectExpressions);

            EloquentSubject::baseQuery($this->subject)->setBindings($selectBindings, 'select');
        }
    }

    /**
     * @param  array<int, string>  $validRequestedIncludes
     * @param  array<string, IncludeInterface>  $includesIndex
     */
    protected function applyValidatedIncludes(array $validRequestedIncludes, array $includesIndex): void
    {
        $relationshipPaths = [];

        foreach ($validRequestedIncludes as $includeName) {
            $include = $includesIndex[$includeName];

            if ($include->getType() === 'relationship') {
                $relationshipPaths[] = $include->getRelation();
            }
        }

        $this->prepareSafeRelationSelectPlan($this->subject->getModel(), $relationshipPaths);

        foreach ($validRequestedIncludes as $includeName) {
            $include = $includesIndex[$includeName];

            $this->registerRuntimeVisibleInclude($includeName, $include);

            $columns = $include->getType() === 'relationship'
                ? $this->getSafeRelationSelectColumns($include->getRelation())
                : null;
            $select = $columns === null ? null : function ($query) use ($columns): void {
                $this->applySafeRelationSelectToQuery($query, $columns);
            };

            if ($include instanceof RelationshipInclude) {
                EagerLoads::merge($this->subject, $include->getRelation(), $select);

                continue;
            }

            $this->subject = $this->applyIncludeKeepingEagerLoads($include, $this->subject);

            if ($select !== null) {
                EagerLoads::merge($this->subject, $include->getRelation(), $select);
            }
        }
    }

    public function getResourceKey(): string
    {
        return $this->resolveDefaultResourceKey($this->subject->getModel());
    }

    /**
     * Root columns the registered eager loads need, or null when the root has to keep all columns.
     *
     * @return array<string>|null
     */
    private function resolveRootEagerLoadColumns(): ?array
    {
        if (! $this->getConfig()->isSafeRelationSelectEnabled()) {
            return [];
        }

        $names = $this->topLevelEagerLoadNames(EloquentSubject::builder($this->subject)->getEagerLoads());

        return $names === [] ? [] : $this->resolveParentColumnsForEagerLoads($this->subject->getModel(), $names);
    }

    /**
     * Qualify column names with table prefix.
     *
     * @param  array<string>  $fields
     * @return array<string>
     */
    protected function qualifyColumns(array $fields): array
    {
        $model = $this->subject->getModel();

        return array_map(
            fn ($field) => $model->qualifyColumn($field),
            $fields
        );
    }

    /**
     * Build relation sparse-fields map/tree once per built wizard.
     */
    private function prepareRelationFieldData(): void
    {
        if ($this->state->relationFieldTreePrepared) {
            return;
        }

        $this->state->relationFieldTreePrepared = true;
        $relationFieldMap = $this->buildValidatedRelationFieldMap();
        $this->state->relationFieldTree = $this->buildRelationFieldTree($relationFieldMap);
    }

    private function prepareAppendTree(): void
    {
        if ($this->state->appendTreePrepared) {
            return;
        }

        $this->state->appendTreePrepared = true;
        $this->state->appendTree = $this->getValidRequestedAppendsTree();
    }

    /**
     * Apply appends and relation sparse fieldsets in a single traversal.
     */
    private function applyPostProcessingToResults(mixed $results): void
    {
        $this->applySafeRootFieldMaskToResults($results);
        $this->prepareAppendTree();
        $this->applyRelationPostProcessingToResults($results, $this->state->appendTree, $this->state->relationFieldTree);
    }

    /**
     * @param  Model|\Traversable<mixed>|array<mixed>  $results
     */
    private function applySafeRootFieldMaskToResults(mixed $results): void
    {
        $rootVisibleFields = $this->state->rootVisibleFields;

        if ($rootVisibleFields !== null) {
            if ($results instanceof Model) {
                $this->hideModelAttributesExcept($results, $rootVisibleFields);
            } else {
                foreach ($results as $item) {
                    if ($item instanceof Model) {
                        $this->hideModelAttributesExcept($item, $rootVisibleFields);
                    }
                }
            }
        }

        $safeRootHiddenFields = $this->state->safeRootHiddenFields;

        if (empty($safeRootHiddenFields)) {
            return;
        }

        if ($results instanceof Model) {
            $results->makeHidden($safeRootHiddenFields);

            return;
        }

        foreach ($results as $item) {
            if ($item instanceof Model) {
                $item->makeHidden($safeRootHiddenFields);
            }
        }
    }

    /**
     * Keep a full root select when root accessors may be serialized as appends.
     *
     * Root accessor dependencies are opaque, so when a root fieldset is narrowed and the
     * response will still expose root appends, the safe option is to fetch full attributes
     * and hide the non-requested fields during post-processing.
     *
     * @param  array<string>  $requestedFields
     */
    private function shouldKeepFullRootSelectForAppends(array $requestedFields): bool
    {
        if ($requestedFields === [] || $requestedFields === ['*']) {
            return false;
        }

        if (! empty($this->subject->getModel()->getAppends())) {
            return true;
        }

        $this->prepareAppendTree();

        return ! empty($this->state->appendTree['appends']);
    }

    /**
     * @param  array<string>  $requestedFields
     * @return array<string>
     */
    private function resolveVisibleRootFields(array $requestedFields): array
    {
        $visibleFields = [];

        foreach ($requestedFields as $field) {
            $normalizedField = $this->normalizePublicPath($field);

            if (isset($this->state->runtimeRootAttributeNamesByField[$normalizedField])) {
                $visibleFields[] = $this->state->runtimeRootAttributeNamesByField[$normalizedField];

                continue;
            }

            $visibleFields[] = $field;
        }

        return array_values(array_unique(array_merge(
            $visibleFields,
            $this->state->alwaysVisibleRuntimeRootAttributes
        )));
    }

    /**
     * @param  array<string>  $fields
     * @param  array<string>  $preservedSelectAliases
     * @return array<string>
     */
    private function excludeRuntimeOnlyRootFieldsFromSelect(array $fields, array $preservedSelectAliases): array
    {
        if (in_array('*', $fields, true)) {
            return $fields;
        }

        $preservedAliasIndex = array_fill_keys($preservedSelectAliases, true);
        $filteredFields = [];

        foreach ($fields as $field) {
            $normalizedField = $this->normalizePublicPath($field);

            if (isset($this->state->runtimeRootAttributeNamesByField[$normalizedField])) {
                continue;
            }

            if (isset($preservedAliasIndex[$field])) {
                continue;
            }

            $filteredFields[] = $field;
        }

        return $filteredFields;
    }

    /**
     * @return array<int, ExpressionContract|string>
     */
    private function collectPreservedSelectExpressions(): array
    {
        $preserved = [];

        foreach (EloquentSubject::baseQuery($this->subject)->columns ?? [] as $column) {
            if (! $this->shouldPreserveSelectedColumn($column)) {
                continue;
            }

            /** @var ExpressionContract|string $column */
            $preserved[] = $column;
        }

        return $preserved;
    }

    /**
     * @param  array<int, ExpressionContract|string>  $columns
     * @return array<string>
     */
    private function collectPreservedSelectAliases(array $columns): array
    {
        $aliases = [];

        foreach ($columns as $column) {
            $alias = $this->extractSelectedColumnAlias($column);

            if ($alias !== null) {
                $aliases[] = $alias;
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @param  array<int, ExpressionContract|string>  $columns
     */
    private function restorePreservedSelectExpressions(array $columns): void
    {
        foreach ($columns as $column) {
            $this->subject->addSelect($column);
        }
    }

    private function shouldPreserveSelectedColumn(mixed $column): bool
    {
        if ($column instanceof Expression) {
            return true;
        }

        if (! is_string($column)) {
            return false;
        }

        return $this->extractSelectedColumnAlias($column) !== null || str_contains($column, '(');
    }

    private function extractSelectedColumnAlias(mixed $column): ?string
    {
        $sql = $this->stringifySelectedColumn($column);

        if ($sql === null) {
            return null;
        }

        if (preg_match('/\bas\s+[`"\\[]?([a-zA-Z0-9_]+)[`"\\]]?\s*$/i', $sql, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function stringifySelectedColumn(mixed $column): ?string
    {
        if ($column instanceof Expression) {
            $sql = $column->getValue(EloquentSubject::baseQuery($this->subject)->getGrammar());

            return is_string($sql) ? $sql : null;
        }

        return is_string($column) ? $column : null;
    }

    private function registerRuntimeVisibleInclude(string $includeName, IncludeInterface $include): void
    {
        if (! in_array($include->getType(), ['count', 'exists'], true)) {
            return;
        }

        $runtimeAttribute = $this->resolveRuntimeAttributeNameForInclude($include);
        $normalizedIncludeName = $this->normalizePublicPath($includeName);

        $this->state->runtimeRootAttributeNamesByField[$normalizedIncludeName] = $runtimeAttribute;

        if (! in_array($runtimeAttribute, $this->state->alwaysVisibleRuntimeRootAttributes, true)) {
            $this->state->alwaysVisibleRuntimeRootAttributes[] = $runtimeAttribute;
        }
    }

    private function resolveRuntimeAttributeNameForInclude(IncludeInterface $include): string
    {
        $relation = str_replace('.', '_', Str::snake($include->getRelation()));

        return "{$relation}_{$include->getType()}";
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
     * @param  callable(): Collection<int, Model>  $executor
     * @return Collection<int, Model>
     */
    private function executeCollectionQuery(callable $executor): Collection
    {
        $this->build();
        $results = $executor();
        $this->applyPostProcessingToResults($results);

        return $results;
    }

    /**
     * @param  callable(): ?Model  $executor
     */
    private function executeNullableModelQuery(callable $executor): ?Model
    {
        $this->build();
        $result = $executor();
        if ($result !== null) {
            $this->applyPostProcessingToResults($result);
        }

        return $result;
    }

    /**
     * @param  callable(): Model  $executor
     */
    private function executeModelQuery(callable $executor): Model
    {
        $this->build();
        $result = $executor();
        $this->applyPostProcessingToResults($result);

        return $result;
    }

    /**
     * @template TPaginator of LengthAwarePaginator|Paginator|CursorPaginator
     *
     * @param  callable(): TPaginator  $executor
     * @return TPaginator
     */
    private function executePaginatorQuery(callable $executor): LengthAwarePaginator|Paginator|CursorPaginator
    {
        $this->build();
        $paginator = $executor();
        $this->applyPostProcessingToResults($paginator->items());

        return $paginator;
    }

    /**
     * Proxy method calls to the underlying query builder.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $this->build();

        $result = $this->subject->$name(...$arguments);

        if ($result === $this->subject) {
            $this->proxyModified = true;

            return $this;
        }

        if ($result instanceof Builder || $result instanceof Relation) {
            $this->subject = $result;
            $this->proxyModified = true;

            return $this;
        }

        return $result;
    }
}
