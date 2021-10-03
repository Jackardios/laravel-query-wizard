<?php

namespace Jackardios\QueryWizard\Tests\Feature\Model;

use Jackardios\QueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\ModelQueryWizard;
use TypeError;

/**
 * @group model
 * @group wizard
 * @group model-wizard
 */
class ModelQueryWizardTest extends TestCase
{
    /** @test */
    public function it_can_not_be_given_not_a_model(): void
    {
        $this->expectException(TypeError::class);

        ModelQueryWizard::for('not a model');
    }
}
