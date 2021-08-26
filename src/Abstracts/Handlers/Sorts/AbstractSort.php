<?php

namespace Jackardios\QueryWizard\Abstracts\Handlers\Sorts;

use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

abstract class AbstractSort
{
    protected string $name;
    protected string $propertyName;

    abstract public function handle($query, string $direction, AbstractQueryHandler $queryHandler): void;

    public function __construct(string $name, ?string $propertyName = null)
    {
        $this->name = $name;
        $this->propertyName = $propertyName ?? $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }
}
