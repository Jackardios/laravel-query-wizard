<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Tests\App\Models\NestedRelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\RelatedModel;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use Jackardios\QueryWizard\Tests\App\Models\TypedScopeModel;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function bool_scope_parameters_read_the_value_as_a_boolean(): void
    {
        $bindings = fn (string $scope, string $value): array => $this
            ->createEloquentWizardFromQuery(['filter' => [$scope => $value]], TypedScopeModel::class)
            ->allowedFilters(EloquentFilter::scope($scope))
            ->toQuery()
            ->getBindings();

        $this->assertSame(['no'], $bindings('flagged', 'false'));
        $this->assertSame(['yes'], $bindings('flagged', 'on'));
        $this->assertSame(['false'], $bindings('flaggedOrNamed', 'false'));
        $this->assertSame([5], $bindings('countOrFlag', '5'));
        $this->assertSame([0], $bindings('countOrFlag', 'no'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function argumentsOfTheWrongType(): array
    {
        return [
            'bool' => ['flagged', 'maybe', 'Expected a boolean for `flag`.'],
            'union' => ['countOrFlag', 'maybe', 'Expected an integer or a boolean for `value`.'],
            'model without binding' => ['user', '1', 'Expected a TestModel for `user`.'],
            'array' => ['namesIn', 'a', 'Expected a list for `names`.'],
            'interface' => ['createdBefore', '2024-01-01', 'Expected a DateTimeInterface for `date`.'],
            'intersection' => ['labelled', 'x', 'Expected a Stringable&DateTimeInterface for `label`.'],
        ];
    }

    #[Test]
    #[DataProvider('argumentsOfTheWrongType')]
    public function arguments_that_do_not_fit_the_parameter_type_are_rejected(string $scope, string $value, string $reason): void
    {
        try {
            $this
                ->createEloquentWizardFromQuery(['filter' => [$scope => $value]], TypedScopeModel::class)
                ->allowedFilters(EloquentFilter::scope($scope))
                ->toQuery();

            $this->fail('Expected InvalidFilterValue');
        } catch (InvalidFilterValue $exception) {
            $this->assertSame($reason, $exception->reason);
        }
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

    #[Test]
    public function a_scope_without_parameters_takes_any_value(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['visible' => 'yes,please'])
            ->allowedFilters(EloquentFilter::scope('visible'))
            ->toQuery()
            ->toSql();

        $this->assertSame('select * from "test_models" where "is_visible" = ?', $sql);
    }

    #[Test]
    #[DataProvider('invalidScopeValues')]
    public function values_that_do_not_fit_the_scope_are_rejected(string $scope, mixed $value, string $reason): void
    {
        try {
            $this
                ->createEloquentWizardWithFilters([$scope => $value])
                ->allowedFilters(EloquentFilter::scope($scope)->withModelBinding())
                ->toQuery();

            $this->fail('Expected InvalidFilterValue to be thrown');
        } catch (InvalidFilterValue $e) {
            $this->assertStringEndsWith($reason, $e->getMessage());
        }
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidScopeValues(): array
    {
        return [
            'too many values' => ['named', 'Moscow, Russia', 'Expected 1 value.'],
            'too few values' => ['createdBetween', '2020-01-01', 'Expected 2 values.'],
            'text for an int' => ['idAbove', 'abc', 'Expected an integer for `id`.'],
            'fraction for an int' => ['idAbove', '1.5', 'Expected an integer for `id`.'],
            'text for a float' => ['idAtLeast', 'abc', 'Expected a number for `id`.'],
            'text in a variadic list' => ['idIn', '1,abc', 'Expected an integer for `ids`.'],
            'null for a required value' => ['userInfo', ['1', null], 'Expected a value for `name`.'],
        ];
    }

    #[Test]
    public function values_that_fit_the_scope_are_passed(): void
    {
        $ids = $this->models->modelKeys();

        $models = $this
            ->createEloquentWizardWithFilters(['idIn' => "{$ids[0]},{$ids[2]}", 'idAbove' => '0', 'idAtLeast' => '1.5'])
            ->allowedFilters(EloquentFilter::scope('idIn'), EloquentFilter::scope('idAbove'), EloquentFilter::scope('idAtLeast'))
            ->get();

        $this->assertEqualsCanonicalizing(
            array_values(array_filter([$ids[0], $ids[2]], fn (int $id) => $id >= 1.5)),
            $models->modelKeys()
        );
    }

    #[Test]
    public function integers_with_leading_zeros_fit_an_int_parameter(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['idAbove' => '00'])
            ->allowedFilters(EloquentFilter::scope('idAbove'))
            ->get();

        $this->assertCount($this->models->count(), $models);
    }

    #[Test]
    public function model_binding_works_for_attribute_scopes(): void
    {
        $model = $this->models->last();

        $models = $this
            ->createEloquentWizardWithFilters(['ownedBy' => $model->id])
            ->allowedFilters(EloquentFilter::scope('ownedBy')->withModelBinding())
            ->get();

        $this->assertSame([$model->id], $models->modelKeys());
    }

    #[Test]
    public function model_binding_reads_the_scope_of_the_related_model(): void
    {
        $model = $this->models->first();
        $related = RelatedModel::factory()->create(['test_model_id' => $model->id]);
        $nested = NestedRelatedModel::factory()->create(['related_model_id' => $related->id]);

        $models = $this
            ->createEloquentWizardWithFilters(['relatedModels.withNested' => $nested->id])
            ->allowedFilters(EloquentFilter::scope('relatedModels.withNested')->withModelBinding())
            ->get();

        $this->assertSame([$model->id], $models->modelKeys());
    }

    #[Test]
    public function a_builder_macro_is_called_as_is(): void
    {
        $builder = TestModel::query();
        $builder->macro('named', fn (Builder $query, string ...$names) => $query->whereIn('name', $names));

        $query = $this
            ->createEloquentWizardWithFilters(['named' => 'a,b'], $builder)
            ->allowedFilters(EloquentFilter::scope('named'))
            ->toQuery();

        $this->assertSame(['a', 'b'], $query->getBindings());
    }
}
