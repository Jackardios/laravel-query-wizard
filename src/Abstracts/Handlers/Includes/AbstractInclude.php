<?php

namespace Jackardios\QueryWizard\Abstracts\Handlers\Includes;

use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

abstract class AbstractInclude
{
    protected string $name;
    protected string $include;

    abstract public function handle(AbstractQueryHandler $queryHandler, $queryBuilder): void;

    public function __construct(string $include, ?string $alias = null)
    {
        $this->include = $include;
        $this->name = !empty($alias) ? $alias : $include;
    }

    /**
     * @param AbstractInclude $include
     *
     * @return static
     */
    public static function makeFromOther(AbstractInclude $include)
    {
        return new static($include->getInclude(), $include->getName());
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getInclude(): string
    {
        return $this->include;
    }

    /**
     * @return AbstractInclude[]
     */
    public function createOther(): array
    {
        return [];
    }
}
