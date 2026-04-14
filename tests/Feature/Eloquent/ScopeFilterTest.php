<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('eloquent')]
#[Group('filter')]
#[Group('scope-filter')]
class ScopeFilterTest extends EloquentFilterTestCase
{
    #[Test]
    public function it_can_filter_by_scope(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['named' => $model->name])
            ->allowedFilters(EloquentFilter::scope('named'))
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function it_can_filter_by_scope_with_alias(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['filter_name' => $model->name])
            ->allowedFilters(EloquentFilter::scope('named')->alias('filter_name'))
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function it_can_filter_by_scope_with_multiple_parameters(): void
    {
        $model = $this->models->first();
        $from = $model->created_at->subDay();
        $to = $model->created_at->addDay();

        $models = $this
            ->createEloquentWizardWithFilters(['created_between' => [$from->toDateTimeString(), $to->toDateTimeString()]])
            ->allowedFilters(EloquentFilter::scope('createdBetween')->alias('created_between'))
            ->get();

        $this->assertTrue($models->contains('id', $model->id));
        $this->assertNotEmpty($models);
    }

    #[Test]
    public function scope_filter_rejects_malformed_nested_payload(): void
    {
        $this->expectException(InvalidFilterQuery::class);
        $this->expectExceptionMessage('Invalid `filter` parameter format');

        $this
            ->createEloquentWizardWithFilters(['named' => ['foo' => ['bar' => 'Alpha']]])
            ->allowedFilters(EloquentFilter::scope('named'))
            ->get();
    }

    #[Test]
    public function scope_filter_can_opt_in_to_structured_input_normalization(): void
    {
        $model = $this->models->first();
        $from = $model->created_at->subDay()->toDateTimeString();
        $to = $model->created_at->addDay()->toDateTimeString();

        $models = $this
            ->createEloquentWizardWithFilters(['created_between' => ['from' => $from, 'to' => $to]])
            ->allowedFilters(
                EloquentFilter::scope('createdBetween')->alias('created_between')
                    ->allowStructuredInput()
                    ->prepareValueWith(static fn (array $value): array => [$value['from'] ?? null, $value['to'] ?? null])
            )
            ->get();

        $this->assertTrue($models->contains('id', $model->id));
        $this->assertNotEmpty($models);
    }

    #[Test]
    public function scope_filter_has_model_binding_disabled_by_default(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['named' => $model->name])
            ->allowedFilters(
                EloquentFilter::scope('named')
            )
            ->get();

        $this->assertCount(1, $models);
    }

    #[Test]
    public function scope_filter_with_model_binding_resolves_valid_id(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['user' => $model->id])
            ->allowedFilters(
                EloquentFilter::scope('user')->withModelBinding()
            )
            ->get();

        $this->assertCount(1, $models);
        $this->assertEquals($model->id, $models->first()->id);
    }

    #[Test]
    public function scope_filter_with_model_binding_throws_for_invalid_id(): void
    {
        $this->expectException(InvalidFilterValue::class);

        $this
            ->createEloquentWizardWithFilters(['user' => 99999])
            ->allowedFilters(
                EloquentFilter::scope('user')->withModelBinding()
            )
            ->get();
    }

    #[Test]
    public function scope_filter_with_model_binding_includes_filter_name_in_exception(): void
    {
        try {
            $this
                ->createEloquentWizardWithFilters(['user' => 99999])
                ->allowedFilters(
                    EloquentFilter::scope('user')->withModelBinding()
                )
                ->get();

            $this->fail('Expected InvalidFilterValue to be thrown');
        } catch (InvalidFilterValue $e) {
            $this->assertEquals('user', $e->filterName);
            $this->assertEquals(99999, $e->filterValue);
            $this->assertStringContainsString('user', $e->getMessage());
        }
    }
}
