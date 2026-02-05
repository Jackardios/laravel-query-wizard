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

        return new static(
            $modelClass::query(),
            app(QueryParametersManager::class),
            app(QueryWizardConfig::class),
            $schema
        );
    }

    // === Execution API ===

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
            $this->applyAppendsTo([$result]);
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
        $this->applyAppendsTo([$result]);

        return $result;
    }

    /**
     * Build and execute query with pagination.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        $this->build();
        $paginator = $this->subject->paginate($perPage);
        $this->applyAppendsTo($paginator->getCollection());

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

        return $paginator;
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

    // === Abstract implementations ===

    protected function normalizeStringToFilter(string $name): FilterInterface
    {
        // String = exact filter by default
        return ExactFilter::make($name);
    }

    protected function normalizeStringToSort(string $name): SortInterface
    {
        // String = field sort by default, strip leading '-' for property
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

    // === Helpers ===

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
     * Proxy method calls to the underlying query builder.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $result = $this->subject->$name(...$arguments);

        // If the method returns the same builder instance, return $this for chaining
        if ($result === $this->subject) {
            return $this;
        }

        // If the method returns a new Builder/Relation, update subject and return $this
        if ($result instanceof Builder || $result instanceof Relation) {
            $this->subject = $result;

            return $this;
        }

        // For terminal methods (count, exists, etc.), return the result directly
        return $result;
    }

    /**
     * Clone the wizard.
     *
     * Creates an independent copy with the same build state.
     * The query builder is cloned to ensure independent state.
     */
    public function __clone(): void
    {
        // Parent handles subject cloning and build state preservation
        parent::__clone();
    }
}
