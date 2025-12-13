<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;
use Jackardios\QueryWizard\Contracts\DriverInterface;
use Jackardios\QueryWizard\Contracts\FilterStrategyInterface;
use Jackardios\QueryWizard\Contracts\IncludeStrategyInterface;
use Jackardios\QueryWizard\Contracts\SortStrategyInterface;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\FilterDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\IncludeDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Definitions\SortDefinition;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\CallbackFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\DateRangeFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\ExactFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\JsonContainsFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\NullFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\PartialFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\RangeFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\ScopeFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\TrashedFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Includes\CallbackIncludeStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Includes\CountIncludeStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Includes\RelationshipIncludeStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Sorts\CallbackSortStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Sorts\FieldSortStrategy;

class EloquentDriver implements DriverInterface
{
    /** @var array<string, class-string<FilterStrategyInterface>> */
    protected array $filterStrategies = [
        'exact' => ExactFilterStrategy::class,
        'partial' => PartialFilterStrategy::class,
        'scope' => ScopeFilterStrategy::class,
        'callback' => CallbackFilterStrategy::class,
        'trashed' => TrashedFilterStrategy::class,
        'range' => RangeFilterStrategy::class,
        'dateRange' => DateRangeFilterStrategy::class,
        'null' => NullFilterStrategy::class,
        'jsonContains' => JsonContainsFilterStrategy::class,
    ];

    /** @var array<string, class-string<IncludeStrategyInterface>> */
    protected array $includeStrategies = [
        'relationship' => RelationshipIncludeStrategy::class,
        'count' => CountIncludeStrategy::class,
        'callback' => CallbackIncludeStrategy::class,
    ];

    /** @var array<string, class-string<SortStrategyInterface>> */
    protected array $sortStrategies = [
        'field' => FieldSortStrategy::class,
        'callback' => CallbackSortStrategy::class,
    ];

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
        return ['filters', 'sorts', 'includes', 'fields', 'appends'];
    }

    // ========== Normalization methods ==========

    /**
     * Normalize a filter definition (string to FilterDefinition)
     */
    public function normalizeFilter(FilterDefinitionInterface|string $filter): FilterDefinitionInterface
    {
        if ($filter instanceof FilterDefinitionInterface) {
            return $filter;
        }

        return FilterDefinition::exact($filter);
    }

    /**
     * Normalize an include definition (string to IncludeDefinition)
     */
    public function normalizeInclude(IncludeDefinitionInterface|string $include): IncludeDefinitionInterface
    {
        if ($include instanceof IncludeDefinitionInterface) {
            return $include;
        }

        $config = app(QueryWizardConfig::class);
        $countSuffix = $config->getCountSuffix();

        if (str_ends_with($include, $countSuffix)) {
            $relation = substr($include, 0, -strlen($countSuffix));
            return IncludeDefinition::count($relation, $include);
        }

        return IncludeDefinition::relationship($include);
    }

    /**
     * Normalize a sort definition (string to SortDefinition)
     */
    public function normalizeSort(SortDefinitionInterface|string $sort): SortDefinitionInterface
    {
        if ($sort instanceof SortDefinitionInterface) {
            return $sort;
        }

        $property = ltrim($sort, '-');
        return SortDefinition::field($property, $sort);
    }

    // ========== Apply methods ==========

    public function applyFilter(mixed $subject, FilterDefinitionInterface $filter, mixed $value): mixed
    {
        $builder = $this->ensureBuilder($subject);
        $strategy = $this->resolveFilterStrategy($filter);

        return $strategy->apply($builder, $filter, $value);
    }

    /**
     * @param array<string> $fields
     */
    public function applyInclude(mixed $subject, IncludeDefinitionInterface $include, array $fields = []): mixed
    {
        $builder = $this->ensureBuilder($subject);
        $strategy = $this->resolveIncludeStrategy($include);

        return $strategy->apply($builder, $include, $fields);
    }

    public function applySort(mixed $subject, SortDefinitionInterface $sort, string $direction): mixed
    {
        $builder = $this->ensureBuilder($subject);
        $strategy = $this->resolveSortStrategy($sort);

        return $strategy->apply($builder, $sort, $direction);
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
        if (empty($appends)) {
            return $result;
        }

        $rootAppends = [];
        /** @var array<string, array<string>> $relationAppends */
        $relationAppends = [];

        foreach ($appends as $append) {
            if (str_contains($append, '.')) {
                $lastDotPos = strrpos($append, '.');
                $relationPath = substr($append, 0, $lastDotPos);
                $appendName = substr($append, $lastDotPos + 1);
                $relationAppends[$relationPath][] = $appendName;
            } else {
                $rootAppends[] = $append;
            }
        }

        if (!empty($rootAppends)) {
            $this->applyAppendsToModels($result, $rootAppends);
        }

        foreach ($relationAppends as $relationPath => $relAppends) {
            $this->applyAppendsToRelation($result, $relationPath, $relAppends);
        }

        return $result;
    }

    /**
     * Apply appends to models (root level)
     *
     * @param array<string> $appends
     */
    protected function applyAppendsToModels(mixed $models, array $appends): void
    {
        if ($models instanceof Model) {
            $models->append($appends);
        } elseif ($models instanceof Collection || is_iterable($models)) {
            foreach ($models as $model) {
                if ($model instanceof Model) {
                    $model->append($appends);
                }
            }
        }
    }

    /**
     * Apply appends to models in a relation (supports nested dot notation)
     *
     * @param array<string> $appends
     */
    protected function applyAppendsToRelation(mixed $models, string $relationPath, array $appends): void
    {
        $parts = explode('.', $relationPath);

        /** @var array<Model> $modelsToProcess */
        $modelsToProcess = [];

        if ($models instanceof Model) {
            $modelsToProcess = [$models];
        } elseif ($models instanceof Collection) {
            $modelsToProcess = $models->all();
        } elseif (is_iterable($models)) {
            foreach ($models as $model) {
                if ($model instanceof Model) {
                    $modelsToProcess[] = $model;
                }
            }
        }

        foreach ($parts as $relationName) {
            /** @var array<Model> $nextModels */
            $nextModels = [];

            foreach ($modelsToProcess as $model) {
                if ($model->relationLoaded($relationName)) {
                    $related = $model->getRelation($relationName);

                    if ($related instanceof Collection) {
                        foreach ($related as $item) {
                            if ($item instanceof Model) {
                                $nextModels[] = $item;
                            }
                        }
                    } elseif ($related instanceof Model) {
                        $nextModels[] = $related;
                    }
                }
            }

            $modelsToProcess = $nextModels;
        }

        foreach ($modelsToProcess as $model) {
            $model->append($appends);
        }
    }

    public function getResourceKey(mixed $subject): string
    {
        $builder = $this->ensureBuilder($subject);
        $model = $builder->getModel();

        $className = class_basename($model);
        return \Illuminate\Support\Str::camel($className);
    }

    /**
     * @return Builder<Model>|Relation<Model>
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
            return $subject->getQuery();
        }

        if ($subject instanceof Model) {
            return $subject->newQuery();
        }

        if (is_string($subject) && is_subclass_of($subject, Model::class)) {
            return $subject::query();
        }

        throw new InvalidArgumentException('Cannot convert subject to Builder');
    }

    protected function resolveFilterStrategy(FilterDefinitionInterface $filter): FilterStrategyInterface
    {
        $type = $filter->getType();

        if ($type === 'custom' && $filter->getStrategyClass() !== null) {
            $class = $filter->getStrategyClass();
            return new $class();
        }

        if (!isset($this->filterStrategies[$type])) {
            throw new InvalidArgumentException("Unknown filter type: $type");
        }

        return new $this->filterStrategies[$type]();
    }

    protected function resolveIncludeStrategy(IncludeDefinitionInterface $include): IncludeStrategyInterface
    {
        $type = $include->getType();

        if ($type === 'custom' && $include->getStrategyClass() !== null) {
            $class = $include->getStrategyClass();
            return new $class();
        }

        if (!isset($this->includeStrategies[$type])) {
            throw new InvalidArgumentException("Unknown include type: $type");
        }

        return new $this->includeStrategies[$type]();
    }

    protected function resolveSortStrategy(SortDefinitionInterface $sort): SortStrategyInterface
    {
        $type = $sort->getType();

        if ($type === 'custom' && $sort->getStrategyClass() !== null) {
            $class = $sort->getStrategyClass();
            return new $class();
        }

        if (!isset($this->sortStrategies[$type])) {
            throw new InvalidArgumentException("Unknown sort type: $type");
        }

        return new $this->sortStrategies[$type]();
    }

    /**
     * Register a custom filter strategy
     *
     * @param class-string<FilterStrategyInterface> $strategyClass
     */
    public function registerFilterStrategy(string $type, string $strategyClass): void
    {
        $this->filterStrategies[$type] = $strategyClass;
    }

    /**
     * Register a custom include strategy
     *
     * @param class-string<IncludeStrategyInterface> $strategyClass
     */
    public function registerIncludeStrategy(string $type, string $strategyClass): void
    {
        $this->includeStrategies[$type] = $strategyClass;
    }

    /**
     * Register a custom sort strategy
     *
     * @param class-string<SortStrategyInterface> $strategyClass
     */
    public function registerSortStrategy(string $type, string $strategyClass): void
    {
        $this->sortStrategies[$type] = $strategyClass;
    }

    public function supportsFilterType(string $type): bool
    {
        return isset($this->filterStrategies[$type]) || $type === 'custom';
    }

    public function supportsIncludeType(string $type): bool
    {
        return isset($this->includeStrategies[$type]) || $type === 'custom';
    }

    public function supportsSortType(string $type): bool
    {
        return isset($this->sortStrategies[$type]) || $type === 'custom';
    }

    /**
     * @return array<string>
     */
    public function getSupportedFilterTypes(): array
    {
        return array_keys($this->filterStrategies);
    }

    /**
     * @return array<string>
     */
    public function getSupportedIncludeTypes(): array
    {
        return array_keys($this->includeStrategies);
    }

    /**
     * @return array<string>
     */
    public function getSupportedSortTypes(): array
    {
        return array_keys($this->sortStrategies);
    }
}
