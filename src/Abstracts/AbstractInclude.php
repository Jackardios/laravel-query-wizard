<?php

namespace Jackardios\QueryWizard\Abstracts;

abstract class AbstractInclude
{
    protected string $name;
    protected string $include;

    public function __construct(string $include, ?string $alias = null)
    {
        $this->include = $include;
        $this->name = !empty($alias) ? $alias : $include;
    }

    public static function makeFromOther(AbstractInclude $include): static
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
    public function createExtra(): array
    {
        return [];
    }
}
