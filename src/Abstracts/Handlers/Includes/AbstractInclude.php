<?php

namespace Jackardios\QueryWizard\Abstracts\Handlers\Includes;

use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

abstract class AbstractInclude
{
    protected string $name;
    protected string $include;

    /** @var mixed */
    protected $default;

    abstract public function handle(AbstractQueryHandler $queryHandler, $query): void;

    public function __construct(string $include, ?string $alias = null, $default = null)
    {
        $this->include = $include;
        $this->name = !empty($alias) ? $alias : $include;
        $this->default = $default;
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
