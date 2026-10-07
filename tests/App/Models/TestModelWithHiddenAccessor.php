<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

/**
 * Test model that hides a computed attribute.
 *
 * Uses the same table as TestModel (test_models).
 */
class TestModelWithHiddenAccessor extends TestModel
{
    protected $table = 'test_models';

    protected $hidden = ['secret_token'];

    public function getSecretTokenAttribute(): string
    {
        return 'SECRET-'.$this->id;
    }
}
