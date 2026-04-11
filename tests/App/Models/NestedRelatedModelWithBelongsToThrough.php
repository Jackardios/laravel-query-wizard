<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Znck\Eloquent\Relations\BelongsToThrough as BelongsToThroughRelation;
use Znck\Eloquent\Traits\BelongsToThrough;

class NestedRelatedModelWithBelongsToThrough extends Model
{
    use BelongsToThrough;

    protected $table = 'nested_related_models';

    protected $guarded = [];

    public $timestamps = false;

    public function relatedModel(): BelongsTo
    {
        return $this->belongsTo(RelatedModel::class);
    }

    public function throughTestModel(): BelongsToThroughRelation
    {
        return $this->belongsToThrough(TestModel::class, RelatedModel::class);
    }
}
