<?php

namespace Jackardios\QueryWizard\Abstracts;

abstract class AbstractFilter
{
    protected string $name;
    protected string $propertyName;

    /**
     * @var mixed|null
     */
    protected mixed $default;

    /**
     * @var callable|null
     */
    protected $prepareValueCallback;

    public function __construct(string $propertyName, ?string $alias = null, $default = null)
    {
        $this->propertyName = $propertyName;
        $this->name = !empty($alias) ? $alias : $propertyName;
        $this->default = $default;
    }

    public static function makeFromOther(AbstractFilter $filter): static
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

    public function default(mixed $value): static
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

    public function prepareValueWith(callable $callback): static
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
    public function createExtra(): array
    {
        return [];
    }
}
