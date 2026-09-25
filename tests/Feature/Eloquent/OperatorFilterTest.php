<?php

declare(strict_types=1);

namespace Jackardios\QueryWizard\Tests\Feature\Eloquent;

use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Eloquent\Filters\OperatorFilter;
use Jackardios\QueryWizard\Enums\FilterOperator;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Tests\App\Models\TestModel;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function dynamic_operator_compares_non_string_scalars_for_equality(): void
    {
        $model = $this->models->first();

        $byId = $this
            ->createEloquentWizardWithFilters(['id' => $model->id])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->toQuery();
        $byFlag = $this
            ->createEloquentWizardWithFilters(['is_visible' => true])
            ->allowedFilters(EloquentFilter::operator('is_visible', FilterOperator::DYNAMIC))
            ->toQuery();

        $this->assertSame('select * from "test_models" where "test_models"."id" = ?', $byId->toSql());
        $this->assertSame([$model->id], $byId->getBindings());
        $this->assertSame('select * from "test_models" where "test_models"."is_visible" = ?', $byFlag->toSql());
        $this->assertSame([true], $byFlag->getBindings());
        $this->assertSame([$model->id], $byId->pluck('id')->all());
    }

    #[Test]
    public function dynamic_operator_compares_prepared_dates_for_equality(): void
    {
        $model = $this->models->first();

        $models = $this
            ->createEloquentWizardWithFilters(['created_at' => 'ignored'])
            ->allowedFilters(
                EloquentFilter::operator('created_at', FilterOperator::DYNAMIC)
                    ->prepareValueWith(fn () => $model->created_at->toDateTimeImmutable())
            )
            ->get();

        $this->assertTrue($models->contains('id', $model->id));
    }

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
        $this->expectExceptionMessage(
            'Filter value `[1,2,3]` is invalid for filter `id`. Lists of values are only supported by the = and != operators.'
        );

        $this
            ->createEloquentWizardWithFilters(['id' => [1, 2, 3]])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::GREATER_THAN))
            ->get();
    }

    #[Test]
    public function a_like_list_matches_any_value(): void
    {
        TestModel::factory()->create(['name' => 'first_match']);
        TestModel::factory()->create(['name' => 'second_match']);

        $names = $this
            ->createEloquentWizardWithFilters(['name' => ['first_m', 'second_m']])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::LIKE))
            ->get()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['first_match', 'second_match'], $names);
    }

    #[Test]
    public function a_relation_like_filter_of_blank_values_adds_no_condition(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['relatedModels.name' => 'x'])
            ->allowedFilters(EloquentFilter::operator('relatedModels.name', FilterOperator::LIKE)->prepareValueWith(fn () => ' '))
            ->toQuery()
            ->toSql();

        $this->assertStringNotContainsString('exists', $sql);
    }

    #[Test]
    public function a_like_list_ignores_whitespace_items(): void
    {
        TestModel::factory()->create(['name' => 'with space']);
        $target = TestModel::factory()->create(['name' => 'first_match']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => ['first_m', ' ']])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::LIKE))
            ->get();

        $this->assertSame([$target->id], $models->modelKeys());
    }

    #[Test]
    public function a_not_like_list_excludes_every_value(): void
    {
        TestModel::factory()->create(['name' => 'first_match']);
        TestModel::factory()->create(['name' => 'second_match']);

        $names = $this
            ->createEloquentWizardWithFilters(['name' => ['first_m', 'second_m']])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::NOT_LIKE))
            ->get()
            ->pluck('name');

        $this->assertCount(5, $names);
        $this->assertNotContains('first_match', $names);
        $this->assertNotContains('second_match', $names);
    }

    #[Test]
    public function like_matches_wildcard_characters_literally(): void
    {
        TestModel::factory()->create(['name' => 'a_c 100%']);
        TestModel::factory()->create(['name' => 'abc 1000']);

        $names = $this
            ->createEloquentWizardWithFilters(['name' => 'a_c 100%'])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::LIKE))
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame(['a_c 100%'], $names);
    }

    #[Test]
    public function a_like_value_is_not_split_unless_asked(): void
    {
        $whole = $this
            ->createEloquentWizardWithFilters(['name' => 'Moscow, Russia'])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::LIKE))
            ->toQuery();

        $split = $this
            ->createEloquentWizardWithFilters(['name' => 'Moscow,Russia'])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::LIKE)->withValueSplitting())
            ->toQuery();

        $this->assertSame(['%Moscow, Russia%'], $whole->getBindings());
        $this->assertSame(['%Moscow%', '%Russia%'], $split->getBindings());
    }

    #[Test]
    public function like_works_on_non_text_columns(): void
    {
        $expected = array_values(array_filter($this->models->modelKeys(), fn (int $id) => str_contains((string) $id, '1')));

        $models = $this
            ->createEloquentWizardWithFilters(['id' => '1'])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::LIKE))
            ->get();

        $this->assertEqualsCanonicalizing($expected, $models->modelKeys());
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

        // PostgreSQL casts the column to text for LIKE
        $this->assertMatchesRegularExpression('/"test_models"\."name"(::text)? LIKE \?/', $sql);
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

    // ========== Dynamic Operator Numeric Validation Tests ==========
    #[Test]
    #[DataProvider('unreadableComparisons')]
    public function dynamic_operator_rejects_operands_that_are_not_numbers_or_dates(string $value, string $reason): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage($reason);

        $this
            ->createEloquentWizardWithFilters(['id' => $value])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->toQuery();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unreadableComparisons(): array
    {
        return [
            'text after >' => ['>text', 'Expected a number or an ISO 8601 date after `>`.'],
            'text after >=' => ['>=text', 'Expected a number or an ISO 8601 date after `>=`.'],
            'text after <' => ['<text', 'Expected a number or an ISO 8601 date after `<`.'],
            'text after <=' => ['<=text', 'Expected a number or an ISO 8601 date after `<=`.'],
            'exponent' => ['>1e3', 'Expected a number or an ISO 8601 date after `>`.'],
            'invalid date' => ['>2024-02-30', 'Expected a number or an ISO 8601 date after `>`.'],
            'operator in a list' => ['>=1,2', 'Operators are not allowed inside a list.'],
        ];
    }

    #[Test]
    #[DataProvider('dateComparisons')]
    public function dynamic_operator_date_names_the_whole_day(string $value, string $sqlOperator, string $bound): void
    {
        $query = $this
            ->createEloquentWizardWithFilters(['created_at' => $value])
            ->allowedFilters(EloquentFilter::operator('created_at', FilterOperator::DYNAMIC))
            ->toQuery();

        $this->assertStringEndsWith("\"created_at\" {$sqlOperator} ?", $query->toSql());
        $this->assertSame([$bound], $query->getBindings());
    }

    #[Test]
    public function dynamic_operator_compares_fractions_and_large_integers_with_an_integer_column(): void
    {
        $ids = fn (string $value): array => $this
            ->createEloquentWizardWithFilters(['id' => $value])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->get()
            ->modelKeys();

        $this->assertEqualsCanonicalizing([3, 4, 5], $ids('>2.5'));
        $this->assertEqualsCanonicalizing([1, 2, 3, 4, 5], $ids('<99999999999999999999'));
        $this->assertSame([], $ids('>=99999999999999999999'));
    }

    #[Test]
    public function dynamic_operator_casts_fractions_to_numeric_on_postgres(): void
    {
        $sql = fn (string $value): string => $this
            ->createEloquentWizardFromQuery(['filter' => ['id' => $value]], $this->postgresQuery())
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->toQuery()
            ->toSql();

        $this->assertStringEndsWith('where "test_models"."id" > CAST(? AS numeric)', $sql('>2.5'));
        $this->assertStringEndsWith('where "test_models"."id" <= CAST(? AS numeric)', $sql('<=99999999999999999999'));
        $this->assertStringEndsWith('where "test_models"."id" > ?', $sql('>2'));
        $this->assertStringEndsWith('where "test_models"."id" = ?', $sql('1.5'));
    }

    #[Test]
    public function dynamic_operator_compares_the_last_four_digit_day(): void
    {
        $midnight = TestModel::factory()->create(['created_at' => '9999-12-31 00:00:00']);
        $last = TestModel::factory()->create(['created_at' => '9999-12-31 23:59:59']);
        $wizard = fn (string $value) => $this
            ->createEloquentWizardWithFilters(['created_at' => $value])
            ->allowedFilters(EloquentFilter::operator('created_at', FilterOperator::DYNAMIC));

        $this->assertSame(TestModel::count(), $wizard('<=9999-12-31')->get()->count());
        $this->assertSame([], $wizard('>9999-12-31')->get()->modelKeys());
        $this->assertSame([$midnight->id, $last->id], $wizard('>9999-12-30')->get()->modelKeys());
        $this->assertSame([$midnight->id, $last->id], $wizard('>=9999-12-31')->get()->modelKeys());
        $this->assertSame(TestModel::count() - 2, $wizard('<9999-12-31')->get()->count());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function dateComparisons(): array
    {
        return [
            'from the day' => ['>=2024-01-31', '>=', '2024-01-31'],
            'after the day' => ['>2024-01-31', '>=', '2024-02-01'],
            'before the day' => ['<2024-01-31', '<', '2024-01-31'],
            'up to the end of the day' => ['<=2024-01-31', '<', '2024-02-01'],
            'up to the end of the year' => ['<=2024-12-31', '<', '2025-01-01'],
        ];
    }

    #[Test]
    public function dynamic_operator_reads_date_times_in_the_app_timezone(): void
    {
        $query = $this
            ->createEloquentWizardWithFilters(['created_at' => '<=2024-01-31T10:15:00+03:00'])
            ->allowedFilters(EloquentFilter::operator('created_at', FilterOperator::DYNAMIC))
            ->toQuery();

        $this->assertStringEndsWith('"created_at" <= ?', $query->toSql());
        $this->assertSame(['2024-01-31 07:15:00'], $query->getConnection()->prepareBindings($query->getBindings()));
    }

    #[Test]
    public function dynamic_operator_binds_numbers(): void
    {
        $query = $this
            ->createEloquentWizardWithFilters(['id' => '> 2.5'])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->toQuery();

        $this->assertSame([2.5], $query->getBindings());

        $models = $this
            ->createEloquentWizardWithFilters(['id' => '> 2'])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->get();

        $this->assertEqualsCanonicalizing([3, 4, 5], $models->modelKeys());
    }

    #[Test]
    public function dynamic_operator_with_a_blank_operand_is_absent(): void
    {
        $sql = $this
            ->createEloquentWizardWithFilters(['id' => '>=  '])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->toQuery()
            ->toSql();

        $this->assertSame('select * from "test_models"', $sql);
    }

    #[Test]
    public function dynamic_operator_accepts_not_equal_with_non_numeric(): void
    {
        // != and <> operators should still work with non-numeric values
        TestModel::factory()->create(['name' => 'unique_test_model']);

        $models = $this
            ->createEloquentWizardWithFilters(['name' => '!=unique_test_model'])
            ->allowedFilters(EloquentFilter::operator('name', FilterOperator::DYNAMIC))
            ->get();

        $this->assertCount(5, $models);
        $this->assertFalse($models->contains('name', 'unique_test_model'));
    }

    #[Test]
    public function dynamic_operator_accepts_numeric_string_for_comparison(): void
    {
        TestModel::factory()->create(['id' => 5000]);

        $models = $this
            ->createEloquentWizardWithFilters(['id' => '>4999'])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->get();

        $this->assertTrue($models->contains('id', 5000));
    }

    #[Test]
    public function dynamic_operator_accepts_negative_numeric_for_comparison(): void
    {
        $models = $this
            ->createEloquentWizardWithFilters(['id' => '>=-100'])
            ->allowedFilters(EloquentFilter::operator('id', FilterOperator::DYNAMIC))
            ->get();

        $this->assertCount(5, $models);
    }

    #[Test]
    public function a_dynamic_value_is_parsed_once_per_application(): void
    {
        $filter = new class('relatedModels.id', null, FilterOperator::DYNAMIC) extends OperatorFilter
        {
            public int $parses = 0;

            protected function parseDynamicOperator(mixed $value): array
            {
                $this->parses++;

                return parent::parseDynamicOperator($value);
            }
        };

        $this->createEloquentWizardWithFilters(['relatedModels.id' => '>=1'])->allowedFilters($filter)->toQuery();

        $this->assertSame(1, $filter->parses);
    }
}
