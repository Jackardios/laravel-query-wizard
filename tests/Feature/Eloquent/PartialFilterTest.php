<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
#[Group('partial-filter')]
class PartialFilterTest extends EloquentFilterTestCase
{
    #[Test]
    public function it_can_filter_by_partial_property(): void
    {
        $uniqueModel = TestModel::factory()->create(['name' => 'UniquePartialTestName']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'UniquePart'])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($uniqueModel->id, $models->first()->id);
    }

    #[Test]
    public function partial_filter_is_case_insensitive(): void
    {
        $uniqueModel = TestModel::factory()->create(['name' => 'CaseInsensitiveTest']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => 'CASEINSENSITIVE'])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($uniqueModel->id, $models->first()->id);
    }

    #[Test]
    public function partial_filter_works_with_array_of_values(): void
    {
        $model1 = TestModel::factory()->create(['name' => 'ArrayPartialAlpha']);
        $model2 = TestModel::factory()->create(['name' => 'ArrayPartialBeta']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => ['ArrayPartialAlpha', 'ArrayPartialBeta']])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertCount(2, $models);
        $this->assertTrue($models->contains('id', $model1->id));
        $this->assertTrue($models->contains('id', $model2->id));
    }

    #[Test]
    public function partial_filter_ignores_empty_values_in_array(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['name' => ['', null, '']])
            ->allowedFilters(EloquentFilter::partial('name'))
            ->get();

        $this->assertEquals(TestModel::count(), $models->count());
    }

    #[Test]
    public function default_filter_works_with_partial_filter(): void
    {
        TestModel::factory()->create(['name' => 'default_partial_test']);

        $models = $this
            ->createEloquentWizardFromQuery()
            ->allowedFilters(EloquentFilter::partial('name')->default('partial'))
            ->get();

        $this->assertTrue($models->contains('name', 'default_partial_test'));
    }

    #[Test]
    public function partial_filter_with_alias(): void
    {
        $uniqueModel = TestModel::factory()->create(['name' => 'AliasPartialTest']);

        $models = $this
            ->createEloquentWizardWithFilters(['search' => 'AliasPartial'])
            ->allowedFilters(EloquentFilter::partial('name')->alias('search'))
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($uniqueModel->id, $models->first()->id);
    }
}
