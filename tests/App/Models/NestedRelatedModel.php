<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jackardios\QueryWizard\Tests\App\data\factories\NestedRelatedModelFactory;

class NestedRelatedModel extends Model
{
    use HasFactory;

    protected static function newFactory(): NestedRelatedModelFactory
    {
        return NestedRelatedModelFactory::new();
    }

    protected $guarded = [];

    public $timestamps = false;

    public function relatedModel(): BelongsTo
    {
        return $this->belongsTo(RelatedModel::class);
    }
}
