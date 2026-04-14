<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

/**
 * Query wizard for Eloquent Builder queries.
 *
 * Handles list queries with filters, sorts, includes, fields, and appends.
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
 *
 * @extends BaseQueryWizard<Builder<Model>|Relation<Model, Model, mixed>>
 *
 * @phpstan-consistent-constructor
 */
class EloquentQueryWizard extends BaseQueryWizard
{
    use HandlesRelationPostProcessing;
    use HandlesSafeRelationSelect;

    /** @var Builder<Model>|Relation<Model, Model, mixed> */
    protected mixed $subject;

    private bool $proxyModified = false;

    /** @var array{fields: array<string>, relations: array<string, mixed>} */
    private array $relationFieldTree = [
        'fields' => [],
        'relations' => [],
    ];

    private bool $relationFieldTreePrepared = false;

    /** @var array{appends: array<string>, relations: array<string, mixed>} */
    private array $appendTree = [
        'appends' => [],
        'relations' => [],
    ];

    private bool $appendTreePrepared = false;

    /** @var array<string> */
    private array $safeRootHiddenFields = [];

    /** @var array<string>|null */
    private ?array $rootVisibleFields = null;

    /** @var array<string, string> */
    private array $runtimeRootAttributeNamesByField = [];

    /** @var array<string> */
    private array $alwaysVisibleRuntimeRootAttributes = [];

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
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<Model>
     */
    public function firstOrFail(array|string $columns = ['*']): Model
    {
        return $this->executeModelQuery(fn () => $this->subject->firstOrFail($columns));
    }

    /**
     * Build and execute query with pagination.
     *
     * @param  array<int, string>  $columns
     */
    public function paginate(
        ?int $perPage = null,
        array $columns = ['*'],
        string $pageName = 'page',
        ?int $page = null,
        \Closure|int|null $total = null
    ): LengthAwarePaginator {
        // Laravel 10 uses func_num_args() to detect if $total was passed.
        // Passing null explicitly causes it to skip count query AND return empty results.
        // Only pass $total when it has a value.
        return $this->executePaginatorQuery(
            fn () => $this->subject->paginate($perPage, $columns, $pageName, $page, ...($total !== null ? [$total] : []))
        );
    }

    /**
     * Build and execute query with simple pagination.
     *
     * @param  array<int, string>  $columns
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
     */
    public function cursorPaginate(
        ?int $perPage = null,
        array $columns = ['*'],
        string $cursorName = 'cursor',
        Cursor|string|null $cursor = null
    ): CursorPaginator {
        return $this->executePaginatorQuery(fn () => $this->subject->cursorPaginate($perPage, $columns, $cursorName, $cursor));
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
     * @return LazyCollection<int, Model>
     */
    public function cursor(): LazyCollection
    {
        $this->build();

        return $this->subject->cursor()->map(function (Model $model) {
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
        $this->ensureChunkByIdColumnSelected($column, $alias);

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
     * Build the query and prepare post-processing trees used after execution.
     *
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function build(): mixed
    {
        $subject = parent::build();
        $this->prepareRelationFieldData();

        return $subject;
    }

    /**
     * Build and return the query builder (without executing).
     *
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function toQuery(): Builder|Relation
    {
        $this->build();

        return $this->subject;
    }

    /**
     * Get the underlying query builder.
     *
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function getSubject(): Builder|Relation
    {
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

        $this->resetSafeRelationSelectState();
        $this->relationFieldTree = $this->emptyRelationFieldTree();
        $this->relationFieldTreePrepared = false;
        $this->appendTree = $this->emptyAppendTree();
        $this->appendTreePrepared = false;
        $this->safeRootHiddenFields = [];
        $this->rootVisibleFields = null;
        $this->runtimeRootAttributeNamesByField = [];
        $this->alwaysVisibleRuntimeRootAttributes = [];
        parent::invalidateBuild();
    }

    public function __clone(): void
    {
        parent::__clone();
        $this->proxyModified = false;
        $this->resetSafeRelationSelectState();
        $this->relationFieldTree = $this->emptyRelationFieldTree();
        $this->relationFieldTreePrepared = false;
        $this->appendTree = $this->emptyAppendTree();
        $this->appendTreePrepared = false;
        $this->safeRootHiddenFields = [];
        $this->rootVisibleFields = null;
        $this->runtimeRootAttributeNamesByField = [];
        $this->alwaysVisibleRuntimeRootAttributes = [];
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
        $this->rootVisibleFields = $this->resolveVisibleRootFields($requestedFields);
        $this->safeRootHiddenFields = [];

        if ($this->shouldKeepFullRootSelectForAppends($requestedFields)) {
            return;
        }

        $preservedSelectExpressions = $this->collectPreservedSelectExpressions();
        $preservedSelectAliases = $this->collectPreservedSelectAliases($preservedSelectExpressions);

        $fields = $this->applySafeRootFieldRequirements($fields);
        $fields = $this->excludeRuntimeOnlyRootFieldsFromSelect($fields, $preservedSelectAliases);

        if (! empty($fields) && $fields !== ['*']) {
            $qualifiedFields = $this->qualifyColumns($fields);
            $this->subject->select($qualifiedFields);
            $this->restorePreservedSelectExpressions($preservedSelectExpressions);
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

            if ($include->getType() !== 'relationship') {
                $this->subject = $include->apply($this->subject);

                continue;
            }

            $relationPath = $include->getRelation();
            $columns = $this->getSafeRelationSelectColumns($relationPath);

            if ($columns === null) {
                $this->subject = $include->apply($this->subject);

                continue;
            }

            $this->subject = $this->subject->with([
                $relationPath => static function ($query) use ($columns): void {
                    $query->select($columns);
                },
            ]);
        }
    }

    public function getResourceKey(): string
    {
        if ($this->schema !== null) {
            return $this->normalizePublicName($this->schema->type());
        }

        return $this->normalizePublicName(Str::camel(class_basename($this->subject->getModel())));
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
        if ($this->relationFieldTreePrepared) {
            return;
        }

        $this->relationFieldTreePrepared = true;
        $relationFieldMap = $this->buildValidatedRelationFieldMap();
        $this->relationFieldTree = $this->buildRelationFieldTree($relationFieldMap);
    }

    private function prepareAppendTree(): void
    {
        if ($this->appendTreePrepared) {
            return;
        }

        $this->appendTreePrepared = true;
        $this->appendTree = $this->getValidRequestedAppendsTree();
    }

    /**
     * Apply appends and relation sparse fieldsets in a single traversal.
     */
    private function applyPostProcessingToResults(mixed $results): void
    {
        $this->applySafeRootFieldMaskToResults($results);
        $this->prepareAppendTree();
        $this->applyRelationPostProcessingToResults($results, $this->appendTree, $this->relationFieldTree);
    }

    /**
     * @param  Model|\Traversable<mixed>|array<mixed>  $results
     */
    private function applySafeRootFieldMaskToResults(mixed $results): void
    {
        if ($this->rootVisibleFields !== null) {
            if ($results instanceof Model) {
                $this->hideModelAttributesExcept($results, $this->rootVisibleFields);
            } else {
                foreach ($results as $item) {
                    if ($item instanceof Model) {
                        $this->hideModelAttributesExcept($item, $this->rootVisibleFields);
                    }
                }
            }
        }

        if (empty($this->safeRootHiddenFields)) {
            return;
        }

        if ($results instanceof Model) {
            $results->makeHidden($this->safeRootHiddenFields);

            return;
        }

        foreach ($results as $item) {
            if ($item instanceof Model) {
                $item->makeHidden($this->safeRootHiddenFields);
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

        return ! empty($this->appendTree['appends']);
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

            if (isset($this->runtimeRootAttributeNamesByField[$normalizedField])) {
                $visibleFields[] = $this->runtimeRootAttributeNamesByField[$normalizedField];

                continue;
            }

            $visibleFields[] = $field;
        }

        return array_values(array_unique(array_merge(
            $visibleFields,
            $this->alwaysVisibleRuntimeRootAttributes
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

            if (isset($this->runtimeRootAttributeNamesByField[$normalizedField])) {
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
     * @return array<int, Expression<float|int|string>|string>
     */
    private function collectPreservedSelectExpressions(): array
    {
        $preserved = [];

        foreach ($this->subject->getQuery()->columns ?? [] as $column) {
            if (! $this->shouldPreserveSelectedColumn($column)) {
                continue;
            }

            /** @var Expression<float|int|string>|string $column */
            $preserved[] = $column;
        }

        return $preserved;
    }

    /**
     * @param  array<int, Expression<float|int|string>|string>  $columns
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
     * @param  array<int, Expression<float|int|string>|string>  $columns
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
            $sql = $column->getValue($this->subject->getQuery()->getGrammar());

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

        $this->runtimeRootAttributeNamesByField[$normalizedIncludeName] = $runtimeAttribute;

        if (! in_array($runtimeAttribute, $this->alwaysVisibleRuntimeRootAttributes, true)) {
            $this->alwaysVisibleRuntimeRootAttributes[] = $runtimeAttribute;
        }
    }

    private function resolveRuntimeAttributeNameForInclude(IncludeInterface $include): string
    {
        $relation = str_replace('.', '_', Str::snake($include->getRelation()));

        return "{$relation}_{$include->getType()}";
    }

    private function ensureChunkByIdColumnSelected(?string $column, ?string $alias): void
    {
        $selectedColumns = $this->subject->getQuery()->columns;

        if ($selectedColumns === null || in_array('*', $selectedColumns, true)) {
            return;
        }

        $columnName = $column ?? $this->subject->getModel()->getKeyName();
        $qualifiedColumn = $this->subject->qualifyColumn($columnName);

        if ($this->queryAlreadySelectsChunkColumn($selectedColumns, $columnName, $qualifiedColumn, $alias)) {
            return;
        }

        if ($alias !== null) {
            $this->subject->addSelect("{$qualifiedColumn} as {$alias}");
            $this->safeRootHiddenFields[] = $alias;

            return;
        }

        $this->subject->addSelect($qualifiedColumn);
        $this->safeRootHiddenFields[] = $columnName;
    }

    /**
     * @param  array<int, mixed>  $selectedColumns
     */
    private function queryAlreadySelectsChunkColumn(
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
