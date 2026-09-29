<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentSort;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
class TrustedDefaultsTest extends TestCase
{
    #[Test]
    public function defaults_apply_without_allowed_lists(): void
    {
        $model = TestModel::factory()->create(['name' => 'b']);
        TestModel::factory()->create(['name' => 'a']);
        RelatedModel::factory()->create(['test_model_id' => $model->id]);

        $wizard = $this->createEloquentWizardFromQuery()
            ->defaultSorts('-name')
            ->defaultIncludes('relatedModels')
            ->defaultFields('id', 'name')
            ->defaultAppends('fullname');

        $first = $wizard->get()->first();

        $this->assertStringEndsWith('order by "test_models"."name" desc', $wizard->toSql());
        $this->assertSame($model->id, $first->id);
        $this->assertSame(['id', 'name', 'fullname', 'related_models'], array_keys($first->toArray()));
    }

    #[Test]
    public function a_default_sort_uses_the_allowed_definition_of_that_name(): void
    {
        $popular = TestModel::factory()->create();
        TestModel::factory()->create();
        RelatedModel::factory()->count(2)->create(['test_model_id' => $popular->id]);

        $result = $this->createEloquentWizardFromQuery()
            ->allowedSorts(EloquentSort::count('relatedModels')->alias('popularity'))
            ->defaultSorts('-popularity')
            ->get();

        $this->assertSame($popular->id, $result->first()->id);
    }

    #[Test]
    public function the_client_still_needs_an_allowed_sort_to_request_a_default_one(): void
    {
        $wizard = $this->createEloquentWizardFromQuery(['sort' => '-id'])->defaultSorts('-id');

        $this->expectException(InvalidSortQuery::class);

        $wizard->get();
    }

    #[Test]
    public function a_default_include_outside_the_allowed_list_does_not_apply_once_includes_are_requested(): void
    {
        $model = TestModel::factory()->create();
        RelatedModel::factory()->create(['test_model_id' => $model->id]);

        $result = $this->createEloquentWizardFromQuery(['include' => 'otherRelatedModels'])
            ->allowedIncludes('otherRelatedModels')
            ->defaultIncludes('relatedModels')
            ->get()
            ->first();

        $this->assertFalse($result->relationLoaded('relatedModels'));
        $this->assertTrue($result->relationLoaded('otherRelatedModels'));
    }
}
