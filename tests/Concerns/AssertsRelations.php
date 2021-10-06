<?php

namespace Jackardios\QueryWizard\Tests\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Tests\TestCase;

/**
 * @mixin TestCase
 */
trait AssertsRelations
{
    protected function assertRelationLoaded(Collection $collection, string $relation): void
    {
        $hasModelWithoutRelationLoaded = $collection
            ->contains(function (Model $model) use ($relation) {
                return ! $model->relationLoaded($relation);
            });

        $this->assertFalse($hasModelWithoutRelationLoaded, "The `{$relation}` relation was expected but not loaded.");
    }
}
