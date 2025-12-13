<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jackardios\QueryWizard\Tests\App\data\factories\RelatedModelFactory;

class RelatedModel extends Model
{
    use HasFactory;

    protected static function newFactory(): RelatedModelFactory
    {
        return RelatedModelFactory::new();
    }
    protected $guarded = [];

    public $timestamps = false;

    /**
     * Get the formatted name attribute
     */
    public function getFormattedNameAttribute(): string
    {
        return 'Formatted: ' . $this->name;
    }

    /**
     * Get the uppercase name attribute
     */
    public function getUpperNameAttribute(): string
    {
        return strtoupper($this->name);
    }

    public function testModel(): BelongsTo
    {
        return $this->belongsTo(TestModel::class);
    }

    public function nestedRelatedModels(): HasMany
    {
        return $this->hasMany(NestedRelatedModel::class);
    }

    public function scopeNamed(Builder $query, string $name): Builder
    {
        return $query->where('name', $name);
    }
}
