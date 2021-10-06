<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Filters;

class CallbackFilter extends AbstractEloquentFilter
{
    /**
     * @var callable a PHP callback of the following signature:
     * `function (\Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler $queryHandler, \Illuminate\Database\Eloquent\Builder $builder, mixed $value)`
     */
    private $callback;

    /**
     * @param string $propertyName
     * @param callable $callback a PHP callback of the following signature:
     * `function (\Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler $queryHandler, \Illuminate\Database\Eloquent\Builder $builder, mixed $value)`
     * @param string|null $alias
     * @param mixed $default
     */
    public function __construct(string $propertyName, callable $callback, ?string $alias = null, $default = null)
    {
        parent::__construct($propertyName, $alias, $default);

        $this->callback = $callback;
    }

    /** {@inheritdoc} */
    public function handle($queryHandler, $queryBuilder, $value): void
    {
        call_user_func($this->callback, $queryHandler, $queryBuilder, $value);
    }
}
