<?php

declare(strict_types=1);

/**
 * Analysed by PHPStan only: the public entry points accept builders and
 * relations of concrete models.
 */

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jackardios\QueryWizard\Eloquent\EloquentQueryWizard;
use Jackardios\QueryWizard\Eloquent\EloquentShape;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;

use function PHPStan\Testing\assertType;

/**
 * @param  Builder<TestModel>  $builder
 * @param  HasMany<RelatedModel, TestModel>  $relation
 */
function publicInputTypes(TestModel $model, Builder $builder, HasMany $relation, EloquentShape $shape): void
{
    EloquentQueryWizard::for(TestModel::class);
    EloquentQueryWizard::for($model);
    EloquentQueryWizard::for($builder);
    EloquentQueryWizard::for($relation);
    EloquentQueryWizard::for($model->relatedModels());
    new EloquentQueryWizard($builder);
    new EloquentQueryWizard($relation);

    assertType(
        'Illuminate\Database\Eloquent\Builder<Illuminate\Database\Eloquent\Model>|Illuminate\Database\Eloquent\Builder<Jackardios\QueryWizard\Tests\App\Models\TestModel>|Illuminate\Database\Eloquent\Relations\Relation<Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Model, mixed>',
        $shape->applyTo($builder)
    );
    $shape->applyTo($relation);
}
