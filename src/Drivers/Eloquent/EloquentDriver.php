<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Drivers\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\Definitions\FilterDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\IncludeDefinitionInterface;
use Jackardios\QueryWizard\Contracts\Definitions\SortDefinitionInterface;
use Jackardios\QueryWizard\Contracts\DriverInterface;
use Jackardios\QueryWizard\Drivers\Concerns\HasFilterStrategies;
use Jackardios\QueryWizard\Drivers\Concerns\HasIncludeStrategies;
use Jackardios\QueryWizard\Drivers\Concerns\HasSortStrategies;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\DateRangeFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\ExactFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\JsonContainsFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\NullFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\PartialFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\RangeFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\ScopeFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Filters\TrashedFilterStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Includes\CountIncludeStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Includes\RelationshipIncludeStrategy;
use Jackardios\QueryWizard\Drivers\Eloquent\Strategies\Sorts\FieldSortStrategy;
use Jackardios\QueryWizard\Strategies\CallbackFilterStrategy;
use Jackardios\QueryWizard\Strategies\CallbackIncludeStrategy;
use Jackardios\QueryWizard\Strategies\CallbackSortStrategy;
use Jackardios\QueryWizard\Strategies\PassthroughFilterStrategy;
use Jackardios\QueryWizard\Enums\Capability;

class EloquentDriver implements DriverInterface
{
    use HasFilterStrategies;
    use HasSortStrategies;
    use HasIncludeStrategies;

    protected ?EloquentDefinitionNormalizer $normalizer = null;
    protected ?EloquentAppendHandler $appendHandler = null;

    public function __construct()
    {
        $this->initializeStrategies();
    }

    /**
     * Initialize the default strategies for Eloquent driver.
     */
    protected function initializeStrategies(): void
    {
        $this->filterStrategies = [
            'exact' => ExactFilterStrategy::class,
            'partial' => PartialFilterStrategy::class,
            'scope' => ScopeFilterStrategy::class,
            'callback' => CallbackFilterStrategy::class,
            'trashed' => TrashedFilterStrategy::class,
            'range' => RangeFilterStrategy::class,
            'dateRange' => DateRangeFilterStrategy::class,
            'null' => NullFilterStrategy::class,
            'jsonContains' => JsonContainsFilterStrategy::class,
            'passthrough' => PassthroughFilterStrategy::class,
        ];

        $this->includeStrategies = [
            'relationship' => RelationshipIncludeStrategy::class,
            'count' => CountIncludeStrategy::class,
            'callback' => CallbackIncludeStrategy::class,
        ];

        $this->sortStrategies = [
            'field' => FieldSortStrategy::class,
            'callback' => CallbackSortStrategy::class,
        ];
    }

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

    protected function getNormalizer(): EloquentDefinitionNormalizer
    {
        if ($this->normalizer === null) {
            $this->normalizer = new EloquentDefinitionNormalizer(app(QueryWizardConfig::class));
        }
        return $this->normalizer;
    }

    protected function getAppendHandler(): EloquentAppendHandler
    {
        if ($this->appendHandler === null) {
            $this->appendHandler = new EloquentAppendHandler();
        }
        return $this->appendHandler;
    }

    /**
     * Normalize a filter definition (string to FilterDefinition)
     */
    public function normalizeFilter(FilterDefinitionInterface|string $filter): FilterDefinitionInterface
    {
        return $this->getNormalizer()->normalizeFilter($filter);
    }

    /**
     * Normalize an include definition (string to IncludeDefinition)
     */
    public function normalizeInclude(
        IncludeDefinitionInterface|string $include,
        ?QueryWizardConfig $config = null
    ): IncludeDefinitionInterface {
        if ($config !== null) {
            return (new EloquentDefinitionNormalizer($config))->normalizeInclude($include);
        }
        return $this->getNormalizer()->normalizeInclude($include);
    }

    /**
     * Normalize a sort definition (string to SortDefinition)
     */
    public function normalizeSort(SortDefinitionInterface|string $sort): SortDefinitionInterface
    {
        return $this->getNormalizer()->normalizeSort($sort);
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

        throw new InvalidArgumentException('Cannot convert subject to Builder');
    }
}
