<?php

namespace Jackardios\QueryWizard\Abstracts\Handlers;

use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Abstracts\Handlers\Filters\AbstractFilter;
use Jackardios\QueryWizard\Abstracts\Handlers\Includes\AbstractInclude;
use Jackardios\QueryWizard\Abstracts\Handlers\Sorts\AbstractSort;

abstract class AbstractQueryHandler
{
    protected AbstractQueryWizard $wizard;

    protected $subject;

    /**
     * @var string[]
     */
    protected static array $baseFilterHandlerClasses = [AbstractFilter::class];

    /**
     * @var string[]
     */
    protected static array $baseIncludeHandlerClasses = [AbstractInclude::class];

    /**
     * @var string[]
     */
    protected static array $baseSortHandlerClasses = [AbstractSort::class];

    /**
     * @return string[]
     */
    public static function getBaseFilterHandlerClasses(): array
    {
        return self::$baseFilterHandlerClasses;
    }

    /**
     * @return string[]
     */
    public static function getBaseIncludeHandlerClasses(): array
    {
        return self::$baseIncludeHandlerClasses;
    }

    /**
     * @return string[]
     */
    public static function getBaseSortHandlerClasses(): array
    {
        return self::$baseSortHandlerClasses;
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
