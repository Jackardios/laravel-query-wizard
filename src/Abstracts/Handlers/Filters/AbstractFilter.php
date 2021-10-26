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

    /**
     * @var callable|null
     */
    protected $prepareValueCallback;

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

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function default($value)
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
     * @param callable $callback
     *
     * @return $this
     */
    public function prepareValueWith(callable $callback)
    {
        $this->prepareValueCallback = $callback;

        return $this;
    }

    public function hasPrepareValueCallback(): bool
    {
        return isset($this->prepareValueCallback);
    }

    public function getPrepareValueCallback(): callable
    {
        return $this->prepareValueCallback;
    }

    /**
     * @return AbstractFilter[]
     */
    public function createOther(): array
    {
        return [];
    }
}
