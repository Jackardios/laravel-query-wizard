<?php

namespace Jackardios\QueryWizard\Abstracts\Handlers\Filters;

use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

abstract class AbstractFilter
{
    protected string $name;
    protected string $propertyName;

    /**
     * @var mixed|null
     */
    protected $default;

    abstract public function handle(AbstractQueryHandler $queryHandler, $queryBuilder, $value): void;

    public function __construct(string $propertyName, ?string $alias = null, $default = null)
    {
        $this->propertyName = $propertyName;
        $this->name = !empty($alias) ? $alias : $propertyName;
        $this->default = $default;
    }

    /**
     * @param AbstractFilter $filter
     *
     * @return static
     */
    public static function makeFromOther(AbstractFilter $filter)
    {
        return new static($filter->getPropertyName(), $filter->getName(), $filter->getDefault());
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    public function default($value): self
    {
        $this->default = $value;

        return $this;
    }

    public function hasDefault(): bool
    {
        return isset($this->default);
    }

    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @return AbstractFilter[]
     */
    public function createOther(): array
    {
        return [];
    }
}
