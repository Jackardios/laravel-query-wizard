<?php

namespace Jackardios\QueryWizard\Abstracts\Handlers;

use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Abstracts\Handlers\Filters\AbstractFilter;
use Jackardios\QueryWizard\Abstracts\Handlers\Includes\AbstractInclude;
use Jackardios\QueryWizard\Abstracts\Handlers\Sorts\AbstractSort;

abstract class AbstractQueryHandler
{
    protected AbstractQueryWizard $wizard;

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
     * @param AbstractQueryWizard $wizard
     * @param mixed $subject
     */
    public function __construct(AbstractQueryWizard $wizard, $subject)
    {
        $this->wizard = $wizard;
        $this->subject = $subject;
    }

    /**
     * @return $this
     */
    abstract public function handle();

    public function getSubject()
    {
        return $this->subject;
    }

    public function getWizard(): AbstractQueryWizard
    {
        return $this->wizard;
    }

    public function handleResult($result)
    {
        return $result;
    }

    public function __clone()
    {
        $this->subject = clone $this->subject;
    }
}
