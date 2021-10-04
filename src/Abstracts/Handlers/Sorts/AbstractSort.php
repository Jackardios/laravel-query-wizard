<?php

namespace Jackardios\QueryWizard\Abstracts\Handlers\Sorts;

use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

abstract class AbstractSort
{
    protected string $name;
    protected string $propertyName;

    abstract public function handle(AbstractQueryHandler $queryHandler, $queryBuilder, string $direction): void;

    public function __construct(string $propertyName, ?string $alias = null)
    {
        $this->propertyName = $propertyName;
        $this->name = !empty($alias) ? $alias : $propertyName;
    }

    /**
     * @param AbstractSort $sort
     *
     * @return static
     */
    public static function makeFromOther(AbstractSort $sort)
    {
        return new static($sort->getPropertyName(), $sort->getName());
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    /**
     * @return AbstractSort[]
     */
    public function createOther(): array
    {
        return [];
    }
}
