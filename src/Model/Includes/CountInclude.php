<?php

namespace Jackardios\QueryWizard\Model\Includes;

use Jackardios\QueryWizard\Model\ModelInclude;

class CountInclude extends ModelInclude
{
    public function __construct(string $include, ?string $alias = null)
    {
        if (empty($alias)) {
            $alias = $include.config('query-wizard.count_suffix');
        }
        parent::__construct($include, $alias);
    }

    /** {@inheritdoc} */
    public function handle($queryWizard, $model): void
    {
        $model->loadCount($this->getInclude());
    }
}
