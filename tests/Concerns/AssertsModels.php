<?php

namespace Jackardios\QueryWizard\Tests\Concerns;

use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Tests\TestCase;

/**
 * @mixin TestCase
 */
trait AssertsModels
{
    protected function assertModelsAttributesEqual(Model $firstModel, Model $secondModel): void
    {
        $this->assertEquals($firstModel->getAttributes(), $secondModel->getAttributes());
    }
}
