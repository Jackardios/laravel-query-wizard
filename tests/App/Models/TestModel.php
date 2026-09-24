<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Jackardios\QueryWizard\Tests\App\data\factories\TestModelFactory;

class TestModel extends Model
{
    use HasFactory;

    protected static function newFactory(): TestModelFactory
    {
        return TestModelFactory::new();
    }

    protected $guarded = [];

    public function relatedModels(): HasMany
    {
        return $this->hasMany(RelatedModel::class);
    }

    public function relatedModelsWithAppends(): HasMany
    {
        return $this->hasMany(RelatedModelWithAppends::class, 'test_model_id');
    }

    public function relatedModel(): BelongsTo
    {
        return $this->belongsTo(RelatedModel::class);
    }

    public function otherRelatedModels(): HasMany
    {
        return $this->hasMany(RelatedModel::class);
    }

    public function relatedThroughPivotModels(): BelongsToMany
    {
        return $this->belongsToMany(RelatedThroughPivotModel::class, 'pivot_models');
    }

    public function relatedThroughPivotModelsWithPivot(): BelongsToMany
    {
        return $this->belongsToMany(RelatedThroughPivotModel::class, 'pivot_models')
            ->withPivot(['location']);
    }

    public function morphModels(): MorphMany
    {
        return $this->morphMany(MorphModel::class, 'parent');
    }

    public function scopeNamed(Builder $query, string $name): Builder
    {
        return $query->where('name', $name);
    }

    public function scopeUser(Builder $query, self $user): Builder
    {
        return $query->where('id', $user->id);
    }

    public function scopeUserInfo(Builder $query, self $user, string $name): Builder
    {
        return $query
            ->where('id', $user->id)
            ->where('name', $name);
    }

    public function scopeIdAbove(Builder $query, int $id): Builder
    {
        return $query->where('id', '>', $id);
    }

    public function scopeIdAtLeast(Builder $query, float $id): Builder
    {
        return $query->where('id', '>=', (int) ceil($id));
    }

    public function scopeIdIn(Builder $query, int ...$ids): Builder
    {
        return $query->whereIn('id', $ids);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    #[Scope]
    protected function ownedBy(Builder $query, self $user): void
    {
        $query->where('id', $user->id);
    }

    public function scopeCreatedBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('created_at', [
            Carbon::parse($from), Carbon::parse($to),
        ]);
    }

    public function getFullnameAttribute(): string
    {
        return 'Full: '.$this->name;
    }

    /**
     * Method that throws an exception when called.
     * Used for testing isRelationProperty() exception handling.
     */
    public function throwingMethod(): never
    {
        throw new \RuntimeException('This method always throws');
    }
}
