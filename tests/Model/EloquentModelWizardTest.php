<?php

namespace Jackardios\QueryWizard\Tests\Model;

use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\EloquentModelWizard;
use TypeError;

/**
 * @group model
 * @group wizard
 * @group model-wizard
 */
class EloquentModelWizardTest extends TestCase
{
    /** @test */
    public function it_can_not_be_given_not_a_model(): void
    {
        $this->expectException(TypeError::class);

        EloquentModelWizard::for('not a model');
    }
}
