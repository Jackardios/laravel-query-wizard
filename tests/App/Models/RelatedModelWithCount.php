<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Test model that always counts its nested related models.
 *
 * Uses the same table as RelatedModel (related_models).
 */
class RelatedModelWithCount extends RelatedModel
{
    protected $table = 'related_models';

    protected $withCount = ['nestedRelatedModels'];

    public function nestedRelatedModels(): HasMany
    {
        return $this->hasMany(NestedRelatedModel::class, 'related_model_id');
    }
}
