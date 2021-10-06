<?php

namespace Jackardios\QueryWizard\Handlers\Eloquent\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

class CallbackSort extends AbstractEloquentSort
{
    /**
     * @var callable(AbstractQueryHandler, Builder, string, string):mixed
     */
    private $callback;

    /**
     * @param string $propertyName
     * @param callable(AbstractQueryHandler, Builder, string, string):mixed $callback
     * @param string|null $alias
     */
    public function __construct(string $propertyName, callable $callback, ?string $alias = null)
    {
        parent::__construct($propertyName, $alias);

        $this->callback = $callback;
    }

    /** {@inheritdoc} */
    public function handle(AbstractQueryHandler $queryHandler, $queryBuilder, string $direction): void
    {
        call_user_func($this->callback, $queryHandler, $queryBuilder, $direction, $this->getPropertyName());
    }
}
