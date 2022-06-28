<?php

namespace Jackardios\QueryWizard\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesFilters;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Concerns\HandlesSorts;
use Jackardios\QueryWizard\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Eloquent\Includes\CountInclude;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Eloquent\Sorts\FieldSort;
use Jackardios\QueryWizard\Exceptions\InvalidSubject;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Values\Sort;

/**
 * @mixin Builder|Relation
 */
class EloquentQueryWizard extends AbstractQueryWizard
{
    use HandlesAppends;
    use HandlesFields;
    use HandlesFilters;
    use HandlesIncludes;
    use HandlesSorts;

    /** @var Builder|Relation */
    protected $subject;

    protected array $baseFilterHandlerClasses = [EloquentFilter::class];
    protected array $baseIncludeHandlerClasses = [EloquentInclude::class];
    protected array $baseSortHandlerClasses = [EloquentSort::class];

    /**
     * @throws \Throwable
     */
    public function __construct($subject, ?QueryParametersManager $parametersManager = null)
    {
        if (is_subclass_of($subject, Model::class)) {
            $subject = $subject::query();
        }

        throw_unless(
            $subject instanceof Builder || $subject instanceof Relation,
            InvalidSubject::make($subject)
        );

        parent::__construct($subject, $parametersManager);
    }

    public function getEloquentBuilder(): Builder
    {
        if ($this->subject instanceof Builder) {
            return $this->subject;
        }

        if ($this->subject instanceof Relation) {
            return $this->subject->getQuery();
        }

        throw InvalidSubject::make($this->subject);
    }

    public function defaultFieldsKey(): string
    {
        return $this->subject->getModel()->getTable();
    }

    public function makeDefaultFilterHandler(string $filterName): ExactFilter
    {
        return new ExactFilter($filterName);
    }

    public function makeDefaultIncludeHandler(string $includeName): CountInclude|RelationshipInclude
    {
        $countSuffix = config('query-wizard.count_suffix');
        if (Str::endsWith($includeName, $countSuffix)) {
            $relation = Str::before($includeName, $countSuffix);
            return new CountInclude($relation, $includeName);
        }
        return new RelationshipInclude($includeName);
    }

    public function makeDefaultSortHandler(string $sortName): FieldSort
    {
        return new FieldSort($sortName);
    }

    public function build(): static
    {
        return $this->handleFields()
            ->handleIncludes()
            ->handleFilters()
            ->handleSorts();
    }

    public function handleForwardedResult(mixed $result)
    {
        if ($result instanceof Model) {
            $this->addAppendsToResults(collect([$result]));
        }

        if ($result instanceof Collection) {
            $this->addAppendsToResults($result);
        }

        if ($result instanceof LengthAwarePaginator
            || $result instanceof Paginator
            || $result instanceof CursorPaginator) {
            $this->addAppendsToResults(collect($result->items()));
        }

        return $result;
    }

    protected function addAppendsToResults(Collection $results): void
    {
        $requestedAppends = $this->getAppends();

        if ($requestedAppends->isNotEmpty()) {
            $results->each(function (Model $result) use ($requestedAppends) {
                return $result->append($requestedAppends->toArray());
            });
        }
    }

    protected function handleFields(): static
    {
        $requestedFields = $this->getFields();
        $defaultFieldsKey = $this->getDefaultFieldsKey();
        $modelFields = $requestedFields->get($defaultFieldsKey);

        if (!empty($modelFields)) {
            $modelFields = $this->prependFieldsWithKey($modelFields);
            $this->subject->select($modelFields);
        }

        return $this;
    }

    protected function handleIncludes(): static
    {
        $requestedIncludes = $this->getIncludes();
        $includeHandlers = $this->getAllowedIncludes();
        $builder = $this->getEloquentBuilder();

        $requestedIncludes->each(function($includeName) use ($includeHandlers, $builder) {
            /** @var EloquentInclude|null $handler */
            $handler = $includeHandlers->get($includeName);
            $handler?->handle($this, $builder);
        });

        return $this;
    }

    protected function handleFilters(): static
    {
        $filterHandlers = $this->getAllowedFilters();
        $builder = $this->getEloquentBuilder();

        $this->getFilters()->each(function ($filterValue, $filterKey) use ($filterHandlers, $builder) {
            if (blank($filterValue)) {
                return;
            }

            /** @var EloquentFilter|null $handler */
            $handler = $filterHandlers->get($filterKey);
            $handler?->handle($this, $builder, $filterValue);
        });

        return $this;
    }

    protected function handleSorts(): static
    {
        $requestedSorts = $this->getSorts();
        $sortHandlers = $this->getAllowedSorts();
        $builder = $this->getEloquentBuilder();

        $requestedSorts->each(function(Sort $sort) use ($sortHandlers, $builder) {
            /** @var EloquentSort|null $handler */
            $handler = $sortHandlers->get($sort->getField());
            $handler?->handle($this, $builder, $sort->getDirection());
        });

        return $this;
    }
}
