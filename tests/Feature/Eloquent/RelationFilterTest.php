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
    public function it_propagates_runtime_exception_from_non_relation_method(): void
    {
        // TestModel has a method 'throwingMethod' that throws RuntimeException.
        // The narrowed catch in isRelationProperty (BadMethodCallException|Error)
        // should NOT catch RuntimeException, so it propagates.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This method always throws');

        $this
            ->createEloquentWizardWithFilters(['throwingMethod.name' => 'test'])
            ->allowedFilters(
                EloquentFilter::exact('throwingMethod.name')
            )
            ->get();
    }

    #[Test]
    public function it_treats_non_existent_method_as_non_relation(): void
    {
        // A dot-notation property where the first part is not a method on the model
        // should be treated as a direct column filter (not a relation).
        $sql = $this
            ->createEloquentWizardWithFilters(['nonExistent.name' => 'test'])
            ->allowedFilters(
                EloquentFilter::exact('nonExistent.name')
            )
            ->toQuery()
            ->toSql();

        // No whereHas — treated as direct column reference
        $this->assertStringNotContainsString('exists', strtolower($sql));
    }
}
