<?php

namespace Jackardios\QueryWizard\Abstracts\Handlers\Filters;

use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

abstract class AbstractFilter
{
    protected string $name;
    protected string $propertyName;

    /** @var mixed */
    protected $default;

    abstract public function handle(AbstractQueryHandler $queryHandler, $query, $value): void;

    public function __construct(string $name, ?string $propertyName = null, $default = null)
    {
        $this->name = $name;
        $this->propertyName = $propertyName ?? $name;
        $this->default = $default;
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
}
