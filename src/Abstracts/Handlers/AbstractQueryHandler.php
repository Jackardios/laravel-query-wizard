<?php

namespace Jackardios\QueryWizard\Abstracts\Handlers;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Abstracts\Handlers\Filters\AbstractFilter;
use Jackardios\QueryWizard\Abstracts\Handlers\Includes\AbstractInclude;
use Jackardios\QueryWizard\Abstracts\Handlers\Sorts\AbstractSort;
use Jackardios\QueryWizard\QueryWizard;

abstract class AbstractQueryHandler
{
    protected QueryWizard $wizard;

    /** @var mixed */
    protected $subject;

    protected static string $baseFilterHandlerClass = AbstractFilter::class;
    protected static string $baseIncludeHandlerClass = AbstractInclude::class;
    protected static string $baseSortHandlerClass = AbstractSort::class;

    public static function getBaseFilterHandlerClass(): string
    {
        return self::$baseFilterHandlerClass;
    }

    public static function getBaseIncludeHandlerClass(): string
    {
        return self::$baseIncludeHandlerClass;
    }

    public static function getBaseSortHandlerClass(): string
    {
        return self::$baseSortHandlerClass;
    }

    /**
     * @param \Jackardios\QueryWizard\QueryWizard $wizard
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation $subject
     */
    public function __construct(QueryWizard $wizard, $subject)
    {
        $this->wizard = $wizard;
        $this->subject = $subject;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getWizard(): QueryWizard
    {
        return $this->wizard;
    }

    public function processResult($result)
    {
        return $result;
    }

    abstract public function makeDefaultFilterHandler(string $filterName): AbstractFilter;

    abstract public function makeDefaultIncludeHandler(string $includeName): AbstractInclude;

    abstract public function makeDefaultSortHandler(string $sortName): AbstractSort;

    abstract public function select(Collection $fields): self;

    abstract public function append(Collection $appends): self;

    abstract public function include(Collection $includes, Collection $handlers): self;

    abstract public function filter(Collection $filters, Collection $handlers): self;

    abstract public function sort(Collection $sorts, Collection $handlers): self;
}
