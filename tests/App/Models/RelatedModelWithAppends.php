<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

/**
 * Test model with built-in $appends for testing model-level appends detection.
 *
 * Uses the same table as RelatedModel (related_models).
 */
class RelatedModelWithAppends extends RelatedModel
{
    protected $table = 'related_models';

    /**
     * Built-in appends that are always included in toArray().
     * The accessor depends on the 'name' attribute.
     */
    protected $appends = ['formatted_name'];
}
