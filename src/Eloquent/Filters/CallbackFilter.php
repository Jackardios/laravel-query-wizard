<?php

namespace Jackardios\QueryWizard\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesFilters;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;

class CallbackFilter extends EloquentFilter
{
    /**
     * @var callable(AbstractQueryWizard&HandlesFilters, Builder, mixed, string):mixed
     */
    private $callback;

    /**
     * @param string $propertyName
     * @param callable(AbstractQueryWizard&HandlesFilters, Builder, mixed, string):mixed $callback
     * @param string|null $alias
     * @param mixed|null $default
     */
    public function __construct(string $propertyName, callable $callback, ?string $alias = null, mixed $default = null)
    {
        parent::__construct($propertyName, $alias, $default);

        $this->callback = $callback;
    }
    
    /** {@inheritdoc} */
    public function handle($queryWizard, Builder $queryBuilder, $value): void
    {
        call_user_func($this->callback, $queryWizard, $queryBuilder, $value, $this->getPropertyName());
    }
}
