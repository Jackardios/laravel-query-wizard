<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Enums\FilterOperator;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
#[Group('operator-filter')]
class OperatorFilterTest extends EloquentFilterTestCase
{
    // ========== Static Operator Tests ==========
    #[Test]
    public function it_can_filter_with_equal_operator(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $model->name])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::EQUAL))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($model->id, $models->first()->id);
    }

    #[Test]
    public function it_can_filter_with_not_equal_operator(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $model->name])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::NOT_EQUAL))
            ->get();

        $this->assertCount(4, $models);
        $this->assertFalse($models->contains('id', $model->id));
    }

    #[Test]
    public function it_can_filter_with_greater_than_operator(): void
    {
        $model = TestModel::factory()->create(['name' => 'test', 'id' => 1000]);

        $models = $this
            ->createEloquentWizardWithFilters(['id' => 999])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::GREATER_THAN))
            ->get();

        $this->assertTrue($models->contains('id', $model->id));
        $this->assertEquals($model->id, $models->first()->id);
    }

    #[Test]
    public function it_can_filter_with_greater_than_or_equal_operator(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => $this->models->first()->id])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::GREATER_THAN_OR_EQUAL))
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function it_can_filter_with_less_than_operator(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => $this->models->last()->id])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::LESS_THAN))
            ->get();

        $this->assertCount(4, $models);
        $this->assertFalse($models->contains('id', $this->models->last()->id));
    }

    #[Test]
    public function it_can_filter_with_less_than_or_equal_operator(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => $this->models->last()->id])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::LESS_THAN_OR_EQUAL))
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function it_can_filter_with_like_operator(): void
    {
        TestModel::factory()->create(['name' => 'unique_test_name']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'unique_test'])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::LIKE))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals('unique_test_name', $models->first()->name);
    }

    #[Test]
    public function it_can_filter_with_not_like_operator(): void
    {
        TestModel::factory()->create(['name' => 'unique_special_name']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'unique_special'])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::NOT_LIKE))
            ->get();

        $this->assertCount(5, $models);
        $this->assertFalse($models->contains('name', 'unique_special_name'));
    }

    // ========== Dynamic Operator Tests ==========
    #[Test]
    public function it_can_parse_dynamic_greater_than_operator(): void
    {
        $model = TestModel::factory()->create(['name' => 'test', 'id' => 2000]);

        $models = $this
            ->createEloquentWizardWithFilters(['id' => '>1999'])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->get();

        $this->assertTrue($models->contains('id', $model->id));
    }

    #[Test]
    public function it_can_parse_dynamic_greater_than_or_equal_operator(): void
    {
        $model = TestModel::factory()->create(['name' => 'test', 'id' => 3000]);

        $models = $this
            ->createEloquentWizardWithFilters(['id' => '>=3000'])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->get();

        $this->assertTrue($models->contains('id', $model->id));
    }

    #[Test]
    public function it_can_parse_dynamic_less_than_operator(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => '<'.$this->models->last()->id])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->get();

        $this->assertCount(4, $models);
    }

    #[Test]
    public function it_can_parse_dynamic_less_than_or_equal_operator(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => '<='.$this->models->last()->id])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function it_can_parse_dynamic_not_equal_operator(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['id' => '!='.$model->id])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->get();

        $this->assertCount(4, $models);
        $this->assertFalse($models->contains('id', $model->id));
    }

    #[Test]
    public function it_can_parse_dynamic_not_equal_operator_with_diamond_notation(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['id' => '<>'.$model->id])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->get();

        $this->assertCount(4, $models);
        $this->assertFalse($models->contains('id', $model->id));
    }

    #[Test]
    public function dynamic_operator_defaults_to_equal_for_plain_values(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $model->name])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::DYNAMIC))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($model->id, $models->first()->id);
    }

    #[Test]
    public function dynamic_operator_skips_empty_value(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => ''])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function dynamic_operator_skips_operator_only_value(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => '>='])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->get();

        $this->assertCount(5, $models);
    }

    // ========== Array Values Tests ==========
    #[Test]
    public function it_can_filter_array_values_with_equal_operator(): void
    {
        $targetModels = $this->models->take(2);
        $names = $targetModels->pluck('name')->toArray();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $names])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::EQUAL))
            ->get();

        $this->assertCount(2, $models);
    }

    #[Test]
    public function it_can_filter_array_values_with_not_equal_operator(): void
    {
        $targetModels = $this->models->take(2);
        $names = $targetModels->pluck('name')->toArray();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $names])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::NOT_EQUAL))
            ->get();

        $this->assertCount(3, $models);
    }

    #[Test]
    public function it_throws_exception_for_array_values_with_greater_than(): void
    {
        $this->expectException(InvalidFilterValue::class);

        $this
            ->createEloquentWizardWithFilters(['id' => [1, 2, 3]])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::GREATER_THAN))
            ->get();
    }

    #[Test]
    public function it_throws_exception_for_array_values_with_like(): void
    {
        $this->expectException(InvalidFilterValue::class);

        $this
            ->createEloquentWizardWithFilters(['name' => ['test1', 'test2']])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::LIKE))
            ->get();
    }

    #[Test]
    public function it_returns_all_for_empty_array(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => []])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::EQUAL))
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function dynamic_operator_treats_array_as_equal(): void
    {
        $targetModels = $this->models->take(2);
        $names = $targetModels->pluck('name')->toArray();

        $models = $this
            ->createEloquentWizardWithFilters(['name' => $names])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::DYNAMIC))
            ->get();

        $this->assertCount(2, $models);
    }

    // ========== SQL Verification Tests ==========
    #[Test]
    public function it_generates_correct_sql_for_greater_than(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['id' => 100])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::GREATER_THAN))
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('"test_models"."id" > ?', $sql);
    }

    #[Test]
    public function it_generates_correct_sql_for_like(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['name' => 'test'])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::LIKE))
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('"test_models"."name" LIKE ?', $sql);
    }

    #[Test]
    public function it_wraps_like_value_with_wildcards(): void
    {
        TestModel::factory()->create(['name' => 'test_substring_value']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'substring'])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::LIKE))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals('test_substring_value', $models->first()->name);
    }

    // ========== Alias and Configuration Tests ==========
    #[Test]
    public function it_can_use_alias(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['price' => $model->id])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::EQUAL)->alias('price'))
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function it_can_use_default_value(): void
    {
        TestModel::factory()->create(['name' => 'default_value']);

        $models = $this
            ->createEloquentWizardFromQuery()
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::EQUAL)->default('default_value'))
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function it_returns_correct_type(): void
    {
        $filter = EloquentFilter::operator('name', FilterOperator::EQUAL);

        $this->assertEquals('operator', $filter->getType());
    }

    #[Test]
    public function it_returns_correct_operator(): void
    {
        $filter = EloquentFilter::operator('name', FilterOperator::GREATER_THAN);

        $this->assertEquals(FilterOperator::GREATER_THAN, $filter->getOperator());
    }
}
