<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
#[Group('relation-filter')]
class RelationFilterTest extends EloquentFilterTestCase
{
    #[Test]
    public function it_can_filter_by_relation_property(): void
    {
        $testModel = $this->models->first();
        RelatedModel::factory()->create([
            'test_model_id' => $testModel->id,
            'name' => 'specific_name',
        ]);

        $models = $this
            ->createEloquentWizardWithFilters(['relatedModels.name' => 'specific_name'])
            ->allowedFilters(EloquentFilter::exact('relatedModels.name'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($testModel->id, $models->first()->id);
    }

    #[Test]
    public function it_can_disable_relation_constraint(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['relatedModels.name' => 'test'])
            ->allowedFilters(
                EloquentFilter::exact('relatedModels.name')
                    ->withoutRelationConstraint()
            )
            ->toQuery()
            ->toSql();

        // Without relation constraint, it should NOT use whereHas
        $this->assertStringNotContainsString('exists', strtolower($sql));
    }

    #[Test]
    public function it_can_filter_by_deeply_nested_relation(): void
    {
        $testModel = $this->models->first();
        $relatedModel = RelatedModel::factory()->create([
            'test_model_id' => $testModel->id,
        ]);
        NestedRelatedModel::factory()->create([
            'related_model_id' => $relatedModel->id,
            'name' => 'deeply_nested',
        ]);

        $models = $this
            ->createEloquentWizardWithFilters(['relatedModels.nestedRelatedModels.name' => 'deeply_nested'])
            ->allowedFilters(EloquentFilter::exact('relatedModels.nestedRelatedModels.name'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($testModel->id, $models->first()->id);
    }

    #[Test]
    public function it_handles_method_that_throws_exception_in_relation_check(): void
    {
        // TestModel has a method 'throwingMethod' that throws an exception when called.
        // The isRelationProperty check should catch this and return false.
        $relationCheckCalled = false;

        $sql = $this
            ->createEloquentWizardWithFilters(['throwingMethod.name' => 'test'])
            ->allowedFilters(
                EloquentFilter::callback('throwingMethod.name', function ($query, $value) use (&$relationCheckCalled) {
                    // If we reach here, the relation check didn't throw
                    $relationCheckCalled = true;
                })
            )
            ->toQuery()
            ->toSql();

        // The callback was invoked, meaning the relation check didn't throw
        $this->assertTrue($relationCheckCalled);
    }
}
