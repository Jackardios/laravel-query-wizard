<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Drivers\AbstractDriver;
use Jackardios\QueryWizard\Drivers\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Drivers\Eloquent\Includes\CountInclude;
use Jackardios\QueryWizard\Drivers\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Drivers\Eloquent\Sorts\FieldSort;
use Jackardios\QueryWizard\Enums\Capability;

class EloquentDriver extends AbstractDriver
{
    /** @var array<string> Supported filter types */
    protected array $supportedFilterTypes = [
        'exact', 'partial', 'scope', 'null', 'range',
        'dateRange', 'jsonContains', 'trashed', 'callback', 'passthrough'
    ];

    /** @var array<string> Supported include types */
    protected array $supportedIncludeTypes = ['relationship', 'count', 'callback'];

    /** @var array<string> Supported sort types */
    protected array $supportedSortTypes = ['field', 'callback'];

    protected ?EloquentAppendHandler $appendHandler = null;
    protected ?QueryWizardConfig $config = null;

    public function name(): string
    {
        return 'eloquent';
    }

    public function supports(mixed $subject): bool
    {
        return $subject instanceof Builder
            || $subject instanceof Relation
            || $subject instanceof Model
            || (is_string($subject) && is_subclass_of($subject, Model::class));
    }

    /**
     * @return array<string>
     */
    public function capabilities(): array
    {
        return Capability::values();
    }

    // ========== Normalization methods ==========

    protected function getConfig(): QueryWizardConfig
    {
        if ($this->config === null) {
            $this->config = app(QueryWizardConfig::class);
        }
        return $this->config;
    }

    protected function getAppendHandler(): EloquentAppendHandler
    {
        if ($this->appendHandler === null) {
            $this->appendHandler = new EloquentAppendHandler();
        }
        return $this->appendHandler;
    }

    /**
     * Normalize a filter definition (string to FilterInterface)
     */
    public function normalizeFilter(FilterInterface|string $filter): FilterInterface
    {
        if ($filter instanceof FilterInterface) {
            return $filter;
        }

        return ExactFilter::make($filter);
    }

    /**
     * Normalize an include definition (string to IncludeInterface)
     */
    public function normalizeInclude(IncludeInterface|string $include): IncludeInterface
    {
        $countSuffix = $this->getConfig()->getCountSuffix();

        if ($include instanceof IncludeInterface) {
            // For count includes without alias, set the alias to relation + suffix
            if ($include->getType() === 'count' && $include->getAlias() === null) {
                return CountInclude::make($include->getRelation(), $include->getRelation() . $countSuffix);
            }
            return $include;
        }

        if (str_ends_with($include, $countSuffix)) {
            $relation = substr($include, 0, -strlen($countSuffix));
            return CountInclude::make($relation, $include);
        }

        return RelationshipInclude::make($include);
    }

    /**
     * Normalize a sort definition (string to SortInterface)
     */
    public function normalizeSort(SortInterface|string $sort): SortInterface
    {
        if ($sort instanceof SortInterface) {
            return $sort;
        }

        $property = ltrim($sort, '-');
        return FieldSort::make($property, $sort);
    }

    // ========== Apply methods ==========

    public function applyFilter(mixed $subject, FilterInterface $filter, mixed $value): mixed
    {
        $builder = $this->ensureBuilder($subject);
        return $filter->apply($builder, $value);
    }

    /**
     * @param array<string> $fields
     */
    public function applyInclude(mixed $subject, IncludeInterface $include, array $fields = []): mixed
    {
        $builder = $this->ensureBuilder($subject);
        return $include->apply($builder, $fields);
    }

    /**
     * @param 'asc'|'desc' $direction
     */
    public function applySort(mixed $subject, SortInterface $sort, string $direction): mixed
    {
        $builder = $this->ensureBuilder($subject);
        return $sort->apply($builder, $direction);
    }

    /**
     * @param array<string> $fields
     */
    public function applyFields(mixed $subject, array $fields): mixed
    {
        $builder = $this->ensureBuilder($subject);

        if (empty($fields)) {
            return $builder;
        }

        if (in_array('*', $fields, true)) {
            return $builder;
        }

        $qualifiedFields = array_map(
            fn(string $field): string => $builder->qualifyColumn($field),
            $fields
        );

        $builder->select($qualifiedFields);

        return $builder;
    }

    /**
     * @param array<string> $appends
     */
    public function applyAppends(mixed $result, array $appends): mixed
    {
        return $this->getAppendHandler()->applyAppends($result, $appends);
    }

    public function getResourceKey(mixed $subject): string
    {
        $builder = $this->ensureBuilder($subject);
        $model = $builder->getModel();

        $className = class_basename($model);
        return \Illuminate\Support\Str::camel($className);
    }

    /**
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function prepareSubject(mixed $subject): Builder|Relation
    {
        // Keep Relations as-is to preserve pivot data etc.
        if ($subject instanceof Relation) {
            return $subject;
        }

        return $this->ensureBuilder($subject);
    }

    /**
     * @return Builder<Model>
     */
    public function ensureBuilder(mixed $subject): Builder
    {
        if ($subject instanceof Builder) {
            return $subject;
        }

        if ($subject instanceof Relation) {
            /** @var Builder<Model> */
            return $subject->getQuery();
        }

        if ($subject instanceof Model) {
            /** @var Builder<Model> */
            return $subject->newQuery();
        }

        if (is_string($subject) && is_subclass_of($subject, Model::class)) {
            /** @var Builder<Model> */
            return $subject::query();
        }

        throw new InvalidArgumentException(
            sprintf(
                'Cannot convert %s to Eloquent Builder. Expected Builder, Relation, Model, or Model class-string.',
                is_object($subject) ? get_class($subject) : gettype($subject)
            )
        );
    }

}
