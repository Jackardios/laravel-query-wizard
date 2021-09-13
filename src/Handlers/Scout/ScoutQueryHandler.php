<?php

namespace Jackardios\QueryWizard\Handlers\Scout;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\AbstractEloquentInclude;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\IncludedCount;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\IncludedRelationship;
use Laravel\Scout\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Exceptions\InvalidSubject;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;
use Jackardios\QueryWizard\Handlers\Scout\Filters\AbstractScoutFilter;
use Jackardios\QueryWizard\Handlers\Scout\Filters\FiltersExact;
use Jackardios\QueryWizard\Handlers\Scout\Sorts\AbstractScoutSort;
use Jackardios\QueryWizard\Handlers\Scout\Sorts\SortsByField;
use Jackardios\QueryWizard\ScoutQueryWizard;
use Jackardios\QueryWizard\Values\Sort;

/**
 * @property ScoutQueryWizard $wizard
 * @property Builder $subject
 * @method ScoutQueryWizard getWizard()
 * @method Builder getSubject()
 */
class ScoutQueryHandler extends AbstractQueryHandler
{
    protected static string $baseFilterHandlerClass = AbstractScoutFilter::class;
    protected static string $baseIncludeHandlerClass = AbstractEloquentInclude::class;
    protected static string $baseSortHandlerClass = AbstractScoutSort::class;

    /** @var callable[] */
    protected array $eloquentQueryCallbacks = [];

    /**
     * @param ScoutQueryWizard $wizard
     * @param Builder $subject
     * @throws \Throwable
     */
    public function __construct(ScoutQueryWizard $wizard, $subject)
    {
        throw_unless(
            $subject instanceof Builder,
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

    /**
     * @return $this
     */
    public function addEloquentQueryCallback(callable $callback): self
    {
        $this->eloquentQueryCallbacks[] = $callback;
        return $this;
    }

    public function handle(): ScoutQueryHandler
    {
        return $this->handleFields()
            ->handleIncludes()
            ->handleFilters()
            ->handleSorts()
            ->handleEloquentQuery();
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

    protected function handleEloquentQuery(): self
    {
        $this->getSubject()->query(function(EloquentBuilder $query) {
            foreach($this->eloquentQueryCallbacks as $callback) {
                call_user_func_array($callback, func_get_args());
            }
        });

        return $this;
    }

    protected function handleFields(): self
    {
        $requestedFields = $this->wizard->getFields();
        $defaultFieldsKey = $this->wizard->getDefaultFieldsKey();
        $modelFields = $requestedFields->get($defaultFieldsKey);

        if (!empty($modelFields)) {
            $modelFields = $this->wizard->prependFieldsWithKey($modelFields);
            $this->addEloquentQueryCallback(function(EloquentBuilder $query) use ($modelFields) {
                return $query->select($modelFields);
            });
        }

        return $this;
    }

    protected function handleIncludes(): self
    {
        $requestedIncludes = $this->wizard->getIncludes();
        $handlers = $this->wizard->getAllowedIncludes();

        $this->addEloquentQueryCallback(function(EloquentBuilder $query) use ($requestedIncludes, $handlers) {
            $requestedIncludes->each(function($include) use (&$query, $handlers) {
                $handler = $handlers->get($include);
                if ($handler) {
                    $handler->handle($this, $query);
                }
            });
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
