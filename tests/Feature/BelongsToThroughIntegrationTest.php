<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature;

use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModelWithBelongsToThrough;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Znck\Eloquent\Traits\BelongsToThrough;

#[Group('integration')]
#[Group('belongs-to-through')]
class BelongsToThroughIntegrationTest extends TestCase
{
    protected Collection $models;

    protected NestedRelatedModelWithBelongsToThrough $nestedModel;

    protected function setUp(): void
    {
        parent::setUp();

        if (! trait_exists(BelongsToThrough::class)) {
            self::markTestSkipped('staudenmeir/belongs-to-through is not installed.');
        }

        $this->models = TestModel::factory()->count(3)->create();

        $relatedModel = null;

        $this->models->each(function (TestModel $model) use (&$relatedModel): void {
            $created = RelatedModel::factory()->count(2)->create([
                'test_model_id' => $model->id,
            ]);

            if ($relatedModel === null) {
                $relatedModel = $created->first();
            }
        });

        $this->nestedModel = NestedRelatedModelWithBelongsToThrough::query()->create([
            'related_model_id' => $relatedModel->id,
            'name' => 'Nested integration',
        ]);
    }

    #[Test]
    public function eloquent_query_wizard_supports_real_belongs_to_through_include_with_sparse_fields(): void
    {
        $models = $this
            ->createEloquentWizardFromQuery([
                'include' => 'throughTestModel',
                'fields' => [
                    'nestedRelatedModelWithBelongsToThrough' => 'id,name',
                    'throughTestModel' => 'id,name',
                ],
            ], NestedRelatedModelWithBelongsToThrough::class)
            ->allowedIncludes('throughTestModel')
            ->allowedFields('id', 'name', 'throughTestModel.id', 'throughTestModel.name')
            ->get();

        $model = $models->first();

        $this->assertInstanceOf(NestedRelatedModelWithBelongsToThrough::class, $model);
        $this->assertTrue($model->relationLoaded('throughTestModel'));
        $this->assertNotNull($model->throughTestModel);
        $this->assertSame($this->nestedModel->relatedModel->test_model_id, $model->throughTestModel->id);
        $this->assertContains('related_model_id', array_keys($model->getAttributes()));
        $this->assertArrayNotHasKey('related_model_id', $model->toArray());

        $relatedAttributes = array_keys($model->throughTestModel->getAttributes());
        $this->assertContains('id', $relatedAttributes);
        $this->assertContains('name', $relatedAttributes);
        $this->assertNotContains('created_at', $relatedAttributes);
    }

    #[Test]
    public function model_query_wizard_supports_real_belongs_to_through_include_with_sparse_fields(): void
    {
        $result = $this
            ->createModelWizardFromQuery([
                'include' => 'throughTestModel',
                'fields' => [
                    'nestedRelatedModelWithBelongsToThrough' => 'id,name',
                    'throughTestModel' => 'id,name',
                ],
            ], $this->nestedModel)
            ->allowedIncludes('throughTestModel')
            ->allowedFields('id', 'name', 'throughTestModel.id', 'throughTestModel.name')
            ->process();

        $this->assertTrue($result->relationLoaded('throughTestModel'));
        $this->assertNotNull($result->throughTestModel);
        $this->assertSame($this->nestedModel->relatedModel->test_model_id, $result->throughTestModel->id);
        $this->assertArrayNotHasKey('related_model_id', $result->toArray());

        $relatedAttributes = array_keys($result->throughTestModel->getAttributes());
        $this->assertContains('id', $relatedAttributes);
        $this->assertContains('name', $relatedAttributes);
        $this->assertNotContains('created_at', $relatedAttributes);
    }
}
