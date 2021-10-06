<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

class CallbackFilter extends AbstractEloquentFilter
{
    /**
     * @var callable(AbstractQueryHandler, Builder, mixed, string):mixed
     */
    private $callback;

    /**
     * @param string $propertyName
     * @param callable(AbstractQueryHandler, Builder, mixed, string):mixed $callback
     * @param string|null $alias
     * @param mixed $default
     */
    public function __construct(string $propertyName, callable $callback, ?string $alias = null, $default = null)
    {
        parent::__construct($propertyName, $alias, $default);

        $this->callback = $callback;
    }

    /** {@inheritdoc} */
    public function handle(AbstractQueryHandler $queryHandler, $queryBuilder, $value): void
    {
        call_user_func($this->callback, $queryHandler, $queryBuilder, $value, $this->getPropertyName());
    }
}
