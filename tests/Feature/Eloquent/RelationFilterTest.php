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

    // ========== RangeFilter Relation Tests ==========

    #[Test]
    public function it_can_filter_by_relation_property_with_range_filter(): void
    {
        $testModel = $this->models->first();
        RelatedModel::factory()->create([
            'test_model_id' => $testModel->id,
        ]);

        $sql = $this
            ->createEloquentWizardWithFilters(['relatedModels.id' => ['min' => 1, 'max' => 100]])
            ->allowedFilters(EloquentFilter::range('relatedModels.id'))
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('>=', $sql);
        $this->assertStringContainsString('<=', $sql);
    }

    #[Test]
    public function it_can_disable_relation_constraint_for_range_filter(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['relatedModels.id' => ['min' => 1]])
            ->allowedFilters(
                EloquentFilter::range('relatedModels.id')
                    ->withoutRelationConstraint()
            )
            ->toQuery()
            ->toSql();

        $this->assertStringNotContainsString('exists', strtolower($sql));
    }

    // ========== DateRangeFilter Relation Tests ==========

    #[Test]
    public function it_can_filter_by_relation_property_with_date_range_filter(): void
    {
        $testModel = $this->models->first();
        RelatedModel::factory()->create([
            'test_model_id' => $testModel->id,
        ]);

        $sql = $this
            ->createEloquentWizardWithFilters(['relatedModels.created_at' => ['from' => '2020-01-01', 'to' => '2030-12-31']])
            ->allowedFilters(EloquentFilter::dateRange('relatedModels.created_at'))
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('>=', $sql);
        $this->assertStringContainsString('<=', $sql);
    }

    #[Test]
    public function it_can_disable_relation_constraint_for_date_range_filter(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['relatedModels.created_at' => ['from' => '2020-01-01']])
            ->allowedFilters(
                EloquentFilter::dateRange('relatedModels.created_at')
                    ->withoutRelationConstraint()
            )
            ->toQuery()
            ->toSql();

        $this->assertStringNotContainsString('exists', strtolower($sql));
    }

    // ========== NullFilter Relation Tests ==========

    #[Test]
    public function it_can_filter_by_relation_property_with_null_filter(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['relatedModels.name' => true])
            ->allowedFilters(EloquentFilter::null('relatedModels.name'))
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('is null', strtolower($sql));
    }

    #[Test]
    public function it_can_disable_relation_constraint_for_null_filter(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['relatedModels.name' => true])
            ->allowedFilters(
                EloquentFilter::null('relatedModels.name')
                    ->withoutRelationConstraint()
            )
            ->toQuery()
            ->toSql();

        $this->assertStringNotContainsString('exists', strtolower($sql));
    }

    #[Test]
    public function it_can_filter_by_relation_property_with_null_filter_inverted(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['relatedModels.name' => true])
            ->allowedFilters(
                EloquentFilter::null('relatedModels.name')
                    ->withInvertedLogic()
            )
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertStringContainsString('is not null', strtolower($sql));
    }

    // ========== PartialFilter Relation Tests ==========

    #[Test]
    public function it_can_filter_by_relation_property_with_partial_filter(): void
    {
        $testModel = $this->models->first();
        RelatedModel::factory()->create([
            'test_model_id' => $testModel->id,
            'name' => 'searchable_text',
        ]);

        $models = $this
            ->createEloquentWizardWithFilters(['relatedModels.name' => 'searchable'])
            ->allowedFilters(EloquentFilter::partial('relatedModels.name'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($testModel->id, $models->first()->id);
    }

    #[Test]
    public function it_can_disable_relation_constraint_for_partial_filter(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['relatedModels.name' => 'test'])
            ->allowedFilters(
                EloquentFilter::partial('relatedModels.name')
                    ->withoutRelationConstraint()
            )
            ->toQuery()
            ->toSql();

        $this->assertStringNotContainsString('exists', strtolower($sql));
    }
}
