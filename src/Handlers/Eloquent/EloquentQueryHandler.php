<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Exceptions\InvalidSubject;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\AbstractEloquentFilter;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\FiltersExact;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\AbstractEloquentInclude;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\IncludedCount;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\IncludedRelationship;
use Jackardios\QueryWizard\Handlers\Eloquent\Sorts\AbstractEloquentSort;
use Jackardios\QueryWizard\Handlers\Eloquent\Sorts\SortsByField;
use Jackardios\QueryWizard\EloquentQueryWizard;
use Jackardios\QueryWizard\Values\Sort;

/**
 * @property EloquentQueryWizard $wizard
 * @property Builder|Relation $subject
 * @method EloquentQueryWizard getWizard()
 * @method Builder|Relation getSubject()
 */
class EloquentQueryHandler extends AbstractQueryHandler
{
    protected static string $baseFilterHandlerClass = AbstractEloquentFilter::class;
    protected static string $baseIncludeHandlerClass = AbstractEloquentInclude::class;
    protected static string $baseSortHandlerClass = AbstractEloquentSort::class;

    /**
     * @param EloquentQueryWizard $wizard
     * @param Builder|Relation $subject
     * @throws \Throwable
     */
    public function __construct(EloquentQueryWizard $wizard, $subject)
    {
        if (is_subclass_of($subject, Model::class)) {
            $subject = $subject::query();
        }

        throw_unless(
            $subject instanceof Builder || $subject instanceof Relation,
            InvalidSubject::make($subject)
        );

        parent::__construct($wizard, $subject);
    }

    public function makeDefaultFilterHandler(string $filterName): FiltersExact
    {
        return new FiltersExact($filterName);
    }

    /**
     * @param string $includeName
     * @return IncludedRelationship|IncludedCount
     */
    public function makeDefaultIncludeHandler(string $includeName): AbstractEloquentInclude
    {
        $countSuffix = config('query-wizard.count_suffix');
        if (Str::endsWith($includeName, $countSuffix)) {
            $relation = Str::before($includeName, $countSuffix);
            return new IncludedCount($relation, $includeName);
        }
        return new IncludedRelationship($includeName);
    }

    public function makeDefaultSortHandler(string $sortName): SortsByField
    {
        return new SortsByField($sortName);
    }

    public function handle(): EloquentQueryHandler
    {
        return $this->handleFields()
            ->handleIncludes()
            ->handleFilters()
            ->handleSorts();
    }

    public function handleResult($result)
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

    protected function handleFields(): self
    {
        $requestedFields = $this->wizard->getFields();
        $defaultFieldKey = $this->wizard->getDefaultFieldKey();
        $modelFields = $requestedFields->get($defaultFieldKey);

        if (!empty($modelFields)) {
            $modelFields = $this->wizard->prependFieldsWithKey($modelFields);
            $this->subject->select($modelFields);
        }

        return $this;
    }

    protected function handleIncludes(): self
    {
        $requestedIncludes = $this->wizard->getIncludes();
        $handlers = $this->wizard->getAllowedIncludes();
        $requestedIncludes->each(function($include) use ($handlers) {
            $handler = $handlers->get($include);
            if ($handler) {
                $handler->handle($this, $this->subject);
            }
        });
        return $this;
    }

    protected function handleFilters(): self
    {
        $requestedFilters = $this->wizard->getFilters();
        $handlers = $this->wizard->getAllowedFilters();
        $requestedFilters->each(function($value, $name) use ($handlers) {
            $handler = $handlers->get($name);
            if ($handler) {
                $handler->handle($this, $this->subject, $value);
            }
        });
        return $this;
    }

    protected function handleSorts(): self
    {
        $requestedSorts = $this->wizard->getSorts();
        $handlers = $this->wizard->getAllowedSorts();

        $requestedSorts->each(function(Sort $sort) use ($handlers) {
            $handler = $handlers->get($sort->getField());
            if ($handler) {
                $handler->handle($this, $this->subject, $sort->getDirection());
            }
        });
        return $this;
    }

    protected function addAppendsToResults(Collection $results): void
    {
        $requestedAppends = $this->wizard->getAppends();

        if ($requestedAppends->isNotEmpty()) {
            $results->each(function (Model $result) use ($requestedAppends) {
                return $result->append($requestedAppends->toArray());
            });
        }
    }
}
