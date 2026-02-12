<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Eloquent;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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
 * @phpstan-consistent-constructor
 */
final class EloquentQueryWizard extends BaseQueryWizard
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
        return new self($subject);
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

        return new self($modelClass::query(), null, null, $schema);
    }

    /**
     * Build and execute query, returning all results.
     *
     * @return Collection<int, Model>
     */
    public function get(): Collection
    {
        return $this->executeCollectionQuery(fn () => $this->subject->get());
    }

    /**
     * Build and execute query, returning first result.
     */
    public function first(): ?Model
    {
        return $this->executeNullableModelQuery(fn () => $this->subject->first());
    }

    /**
     * Build and execute query, returning first result or throwing exception.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<Model>
     */
    public function firstOrFail(): Model
    {
        return $this->executeModelQuery(fn () => $this->subject->firstOrFail());
    }

    /**
     * Build and execute query with pagination.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->executePaginatorQuery(fn () => $this->subject->paginate($perPage));
    }

    /**
     * Build and execute query with simple pagination.
     */
    public function simplePaginate(int $perPage = 15): Paginator
    {
        return $this->executePaginatorQuery(fn () => $this->subject->simplePaginate($perPage));
    }

    /**
     * Build and execute query with cursor pagination.
     */
    public function cursorPaginate(int $perPage = 15): CursorPaginator
    {
        return $this->executePaginatorQuery(fn () => $this->subject->cursorPaginate($perPage));
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
        $fields = $this->applySafeRootFieldRequirements($fields);
        $this->safeRootHiddenFields = array_values(array_diff($fields, $requestedFields));

        if (! empty($fields) && $fields !== ['*']) {
            $qualifiedFields = $this->qualifyColumns($fields);
            $this->subject->select($qualifiedFields);
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
            return $this->schema->type();
        }

        return Str::camel(class_basename($this->subject->getModel()));
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
