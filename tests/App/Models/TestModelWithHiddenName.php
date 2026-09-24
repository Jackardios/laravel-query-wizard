<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

/**
 * Test model that hides its name.
 *
 * Uses the same table as TestModel (test_models).
 */
class TestModelWithHiddenName extends TestModel
{
    protected $table = 'test_models';

    protected $hidden = ['name'];
}
