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
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\FiltersExact;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\AbstractEloquentInclude;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\IncludedRelationship;
use Jackardios\QueryWizard\Handlers\Eloquent\Sorts\AbstractEloquentSort;
use Jackardios\QueryWizard\Handlers\Eloquent\Sorts\SortsByField;
use Jackardios\QueryWizard\QueryWizard;
use Jackardios\QueryWizard\Values\Sort;

class EloquentQueryHandler extends AbstractQueryHandler
{
    protected ?Collection $appends = null;

    protected static string $baseFilterHandlerClass = AbstractEloquentFilter::class;
    protected static string $baseIncludeHandlerClass = AbstractEloquentInclude::class;
    protected static string $baseSortHandlerClass = AbstractEloquentSort::class;

    /** @var \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation */
    protected $subject;

    /**
     * @param \Jackardios\QueryWizard\QueryWizard $wizard
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation $subject
     * @throws \Throwable
     */
    public function __construct(QueryWizard $wizard, $subject)
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

    public function append(Collection $appends): self
    {
        $this->appends = $appends;
        return $this;
    }

    public function select(Collection $fields): self
    {
        $modelFields = $fields->get($this->wizard->getDefaultFieldKey());

        if (!empty($modelFields)) {
            $this->subject->select($modelFields);
        }

        return $this;
    }

    public function include(Collection $includes, Collection $handlers): self
    {
        $includes->each(function($include) use ($handlers) {
            $handler = $handlers->get($include);
            if ($handler) {
                $handler->handle($this->subject, $this);
            }
        });
        return $this;
    }

    public function filter(Collection $filters, Collection $handlers): self
    {
        $filters->each(function($value, $name) use ($handlers) {
            $handler = $handlers->get($name);
            if ($handler) {
                $handler->handle($this->subject, $value, $this);
            }
        });
        return $this;
    }

    public function sort(Collection $sorts, Collection $handlers): self
    {
        $sorts->each(function(Sort $sort) use ($handlers) {
            $handler = $handlers->get($sort->getField());
            if ($handler) {
                $handler->handle($this->subject, $sort->getDirection(), $this);
            }
        });
        return $this;
    }

    protected function addAppendsToResults(Collection $results): void
    {
        if ($this->appends) {
            $results->each(function (Model $result) {
                return $result->append($this->appends->toArray());
            });
        }
    }

    public function processResult($result)
    {
        if ($result instanceof Model) {
            $this->addAppendsToResults(collect([$result]));
        }

        if ($result instanceof Collection) {
            $this->addAppendsToResults($result);
        }

        if ($result instanceof LengthAwarePaginator || $result instanceof Paginator || $result instanceof CursorPaginator) {
            $this->addAppendsToResults(collect($result->items()));
        }

        return $result;
    }

    public function makeDefaultFilterHandler(string $filterName): FiltersExact
    {
        return new FiltersExact($filterName);
    }

    public function makeDefaultIncludeHandler(string $includeName): IncludedRelationship
    {
        return new IncludedRelationship($includeName);
    }

    public function makeDefaultSortHandler(string $sortName): SortsByField
    {
        return new SortsByField($sortName);
    }
}
