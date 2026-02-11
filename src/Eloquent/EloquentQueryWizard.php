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
use Illuminate\Support\Str;
use Jackardios\QueryWizard\BaseQueryWizard;
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
    /** @var Builder<Model>|Relation<Model, Model, mixed> */
    protected mixed $subject;

    private bool $proxyModified = false;

    /** @var array<string, array<string>> */
    private array $relationFieldMap = [];

    private bool $relationFieldMapPrepared = false;

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

        return new self(
            $modelClass::query(),
            app(QueryParametersManager::class),
            app(QueryWizardConfig::class),
            $schema
        );
    }

    /**
     * Build and execute query, returning all results.
     *
     * @return Collection<int, Model>
     */
    public function get(): Collection
    {
        $this->build();
        $results = $this->subject->get();
        $this->applyAppendsTo($results);
        $this->applyRelationFieldMapToResults($results);

        return $results;
    }

    /**
     * Build and execute query, returning first result.
     */
    public function first(): ?Model
    {
        $this->build();
        $result = $this->subject->first();
        if ($result !== null) {
            $this->applyAppendsTo($result);
            $this->applyRelationFieldMapToResults($result);
        }

        return $result;
    }

    /**
     * Build and execute query, returning first result or throwing exception.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException<Model>
     */
    public function firstOrFail(): Model
    {
        $this->build();
        $result = $this->subject->firstOrFail();
        $this->applyAppendsTo($result);
        $this->applyRelationFieldMapToResults($result);

        return $result;
    }

    /**
     * Build and execute query with pagination.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        $this->build();
        $paginator = $this->subject->paginate($perPage);
        $this->applyAppendsTo($paginator->items());
        $this->applyRelationFieldMapToResults($paginator->items());

        return $paginator;
    }

    /**
     * Build and execute query with simple pagination.
     */
    public function simplePaginate(int $perPage = 15): Paginator
    {
        $this->build();
        $paginator = $this->subject->simplePaginate($perPage);
        $this->applyAppendsTo($paginator->items());
        $this->applyRelationFieldMapToResults($paginator->items());

        return $paginator;
    }

    /**
     * Build and execute query with cursor pagination.
     */
    public function cursorPaginate(int $perPage = 15): CursorPaginator
    {
        $this->build();
        $paginator = $this->subject->cursorPaginate($perPage);
        $this->applyAppendsTo($paginator->items());
        $this->applyRelationFieldMapToResults($paginator->items());

        return $paginator;
    }

    /**
     * Build the query and prepare relation field map used after execution.
     *
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function build(): mixed
    {
        $subject = parent::build();
        $this->prepareRelationFieldMap();

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

        $this->relationFieldMap = [];
        $this->relationFieldMapPrepared = false;
        parent::invalidateBuild();
    }

    public function __clone(): void
    {
        parent::__clone();
        $this->proxyModified = false;
        $this->relationFieldMap = [];
        $this->relationFieldMapPrepared = false;
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
        return RelationshipInclude::fromString($name, $this->config->getCountSuffix());
    }

    protected function applyFields(array $fields): void
    {
        if (! empty($fields) && $fields !== ['*']) {
            $qualifiedFields = $this->qualifyColumns($fields);
            $this->subject->select($qualifiedFields);
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
     * Build a map of relation paths to requested sparse fields.
     *
     * The map is only built when relation field filtering is explicitly configured
     * through dotted allowed fields (e.g. "posts.id") or wildcard allowed fields.
     */
    private function prepareRelationFieldMap(): void
    {
        if ($this->relationFieldMapPrepared) {
            return;
        }

        $this->relationFieldMapPrepared = true;
        $this->relationFieldMap = $this->buildValidatedRelationFieldMap();
    }

    /**
     * Apply relation sparse fieldsets to query results.
     */
    private function applyRelationFieldMapToResults(mixed $results): void
    {
        if (empty($this->relationFieldMap)) {
            return;
        }

        $visited = [];

        if ($results instanceof Model) {
            $this->applyRelationFieldMapRecursively($results, $visited);

            return;
        }

        foreach ($results as $item) {
            if ($item instanceof Model) {
                $this->applyRelationFieldMapRecursively($item, $visited);
            }
        }
    }

    /**
     * @param  array<int, bool>  $visited
     */
    private function applyRelationFieldMapRecursively(Model $model, array &$visited, string $prefix = ''): void
    {
        $objectId = spl_object_id($model);
        if (isset($visited[$objectId])) {
            return;
        }
        $visited[$objectId] = true;

        foreach ($model->getRelations() as $relationName => $relatedData) {
            $relationPath = $prefix === '' ? $relationName : $prefix.'.'.$relationName;
            $visibleFields = $this->relationFieldMap[$relationPath] ?? [];

            if (! empty($visibleFields) && ! in_array('*', $visibleFields, true)) {
                $this->applyVisibleFieldsToRelated($relatedData, $visibleFields);
            }

            if ($relatedData instanceof Model) {
                $this->applyRelationFieldMapRecursively($relatedData, $visited, $relationPath);

                continue;
            }

            if (! is_iterable($relatedData)) {
                continue;
            }

            foreach ($relatedData as $item) {
                if ($item instanceof Model) {
                    $this->applyRelationFieldMapRecursively($item, $visited, $relationPath);
                }
            }
        }
    }

    /**
     * @param  array<string>  $visibleFields
     */
    private function applyVisibleFieldsToRelated(mixed $relatedData, array $visibleFields): void
    {
        if ($relatedData instanceof Model) {
            $this->hideModelAttributesExcept($relatedData, $visibleFields);

            return;
        }

        if (! is_iterable($relatedData)) {
            return;
        }

        foreach ($relatedData as $item) {
            if ($item instanceof Model) {
                $this->hideModelAttributesExcept($item, $visibleFields);
            }
        }
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
