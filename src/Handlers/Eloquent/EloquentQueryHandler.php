<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Exceptions\InvalidSubject;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\AbstractEloquentFilter;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\AbstractEloquentInclude;
use Jackardios\QueryWizard\Handlers\Eloquent\Sorts\AbstractEloquentSort;
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
     * @param Model|Builder|Relation|string $subject
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
        $defaultFieldsKey = $this->wizard->getDefaultFieldsKey();
        $modelFields = $requestedFields->get($defaultFieldsKey);

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
                $handler->handle($this, $this->getEloquentBuilder());
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
                $handler->handle($this, $this->getEloquentBuilder(), $value);
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
                $handler->handle($this, $this->getEloquentBuilder(), $sort->getDirection());
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
