<?php

namespace Jackardios\QueryWizard\Handlers\Model\Includes;

class IncludedCount extends AbstractModelInclude
{
    public function __construct(string $include, ?string $alias = null)
    {
        if (empty($alias)) {
            $alias = $include.config('query-wizard.count_suffix');
        }
        parent::__construct($include, $alias);
    }

    public function handle($queryHandler, $model): void
    {
        $model->loadCount($this->getInclude());
    }
}
