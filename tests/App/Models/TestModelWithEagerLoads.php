<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Test model that always eager loads its related models.
 *
 * Uses the same table as TestModel (test_models).
 */
class TestModelWithEagerLoads extends TestModel
{
    protected $table = 'test_models';

    protected $with = ['relatedModels'];

    public function relatedModels(): HasMany
    {
        return $this->hasMany(RelatedModel::class, 'test_model_id');
    }

    public function eagerRelatedModels(): HasMany
    {
        return $this->hasMany(RelatedModelWithEagerLoads::class, 'test_model_id');
    }

    public function countedRelatedModels(): HasMany
    {
        return $this->hasMany(RelatedModelWithCount::class, 'test_model_id');
    }

    public function markedRelatedModels(): HasMany
    {
        return $this->hasMany(RelatedModel::class, 'test_model_id')
            ->select('related_models.*')
            ->selectRaw("'marked' as marker");
    }
}
