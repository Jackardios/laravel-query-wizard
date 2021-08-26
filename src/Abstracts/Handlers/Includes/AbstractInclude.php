<?php

namespace Jackardios\QueryWizard\Abstracts\Handlers\Includes;

use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

abstract class AbstractInclude
{
    protected string $name;
    protected string $relationship;

    /** @var mixed */
    protected $default;

    abstract public function handle(AbstractQueryHandler $queryHandler, $query): void;

    public function __construct(string $name, ?string $relationship = null, $default = null)
    {
        $this->name = $name;
        $this->relationship = $relationship ?? $name;
        $this->default = $default;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRelationship(): string
    {
        return $this->relationship;
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
